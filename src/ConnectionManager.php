<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateTimeImmutable;
use LC\Portal\Exception\ConnectionManagerException;
use LC\Portal\OpenVpn\ClientConfig;
use LC\Portal\WireGuard\WgClientConfig;

/**
 * List, add and remove connections.
 */
class ConnectionManager
{
    protected RandomInterface $random;
    private Config $config;
    private Storage $storage;
    private VpnDaemon $vpnDaemon;

    public function __construct(Config $config, VpnDaemon $vpnDaemon, Storage $storage)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->vpnDaemon = $vpnDaemon;
        $this->random = new Random();
    }

    /**
     * @return array<string,array<array{user_id:string,connection_id:string,display_name:string,ip_list:array<string>}>>
     */
    public function get(bool $showAll = false): array
    {
        $connectionList = [];
        // keep the record of all nodeBaseUrls we talked to so we only hit them
        // once... multiple profiles can have the same nodeBaseUrl if the run
        // on the same machine/VM
        foreach ($this->config->profileConfigList() as $profileConfig) {
            $profileId = $profileConfig->profileId();
            $connectionList[$profileId] = [];

            if ('openvpn' === $profileConfig->vpnProto()) {
                // OpenVPN
                $certificateList = $this->storage->oCertListByProfileId($profileId);
                $daemonConnectionList = $this->vpnDaemon->oConnectionList($profileConfig->nodeBaseUrl());

                $o = [];
                foreach ($daemonConnectionList['connection_list'] as $connectionEntry) {
                    $o[$connectionEntry['common_name']] = $connectionEntry;
                }

                foreach ($certificateList as $cl) {
                    if (\array_key_exists($cl['common_name'], $o)) {
                        // found it!
                        $commonName = $cl['common_name'];
                        $connectionList[$profileId][] = [
                            'user_id' => $cl['user_id'],
                            'connection_id' => $cl['common_name'],
                            'display_name' => $cl['display_name'],
                            'ip_list' => [$o[$commonName]['ip_four'], $o[$commonName]['ip_six']],
                        ];
                    }
                }

                continue;
            }

            // WireGuard
            $storageWgPeerList = $this->storage->wPeerListByProfileId($profileId);
            $daemonWgPeerList = $this->vpnDaemon->wPeerList($profileConfig->nodeBaseUrl(), $showAll);

            $w = [];
            foreach ($daemonWgPeerList['peer_list'] as $peerEntry) {
                $w[$peerEntry['public_key']] = $peerEntry;
            }

            foreach ($storageWgPeerList as $pl) {
                if (\array_key_exists($pl['public_key'], $w)) {
                    // found it!
                    $connectionList[$profileId][] = [
                        'user_id' => $pl['user_id'],
                        'connection_id' => $pl['public_key'],
                        'display_name' => $pl['display_name'],
                        'ip_list' => [$pl['ip_four'], $pl['ip_six']],
                    ];
                }
            }
        }

        return $connectionList;
    }

    /**
     * Remove and disconnect all VPN clients connected with this
     * OAuth authorization ("auth_key"). This works because VPN
     * clients are not allowed to be connected more than once.
     * In the normal situation there is only 1 active connection
     * when "/disconnect" is called, but in case a previous
     * "/disconnect" was never sent, e.g. because the client
     * crashed this also functions as a "cleanup" of sorts.
     */
    public function disconnectByAuthKey(string $authKey): void
    {
        // OpenVPN
        foreach ($this->storage->oCertListByAuthKey($authKey) as $oCertInfo) {
            $this->disconnect(
                $oCertInfo['user_id'],
                $oCertInfo['profile_id'],
                $oCertInfo['common_name']
            );
        }

        // WireGuard
        foreach ($this->storage->wPeerListByAuthKey($authKey) as $wPeerInfo) {
            $this->disconnect(
                $wPeerInfo['user_id'],
                $wPeerInfo['profile_id'],
                $wPeerInfo['public_key']
            );
        }
    }

    public function disconnectByUserId(string $userId): void
    {
        // OpenVPN
        foreach ($this->storage->oCertListByUserId($userId) as $oCertInfo) {
            $this->disconnect(
                $userId,
                $oCertInfo['profile_id'],
                $oCertInfo['common_name']
            );
        }

        // WireGuard
        foreach ($this->storage->wPeerListByUserId($userId) as $wPeerInfo) {
            $this->disconnect(
                $userId,
                $wPeerInfo['profile_id'],
                $wPeerInfo['public_key']
            );
        }
    }

    public function connect(ServerInfo $serverInfo, string $userId, string $profileId, string $displayName, DateTimeImmutable $expiresAt, bool $tcpOnly, ?string $publicKey, ?string $authKey): ClientConfigInterface
    {
        if (!$this->config->hasProfile($profileId)) {
            throw new ConnectionManagerException('profile "'.$profileId.'" does not exist');
        }
        $profileConfig = $this->config->profileConfig($profileId);
        if ('openvpn' === $profileConfig->vpnProto()) {
            $commonName = Base64UrlSafe::encodeUnpadded($this->random->get(32));
            $certInfo = $serverInfo->ca()->clientCert($commonName, $profileConfig->profileId(), $expiresAt);
            $this->storage->oCertAdd(
                $userId,
                $profileId,
                $commonName,
                $displayName,
                $expiresAt,
                $authKey
            );

            // this thing can throw an ClientConfigException!
            return new ClientConfig(
                $profileConfig,
                $serverInfo->ca()->caCert(),
                $serverInfo->tlsCrypt(),
                $certInfo,
                $tcpOnly,
                ClientConfig::STRATEGY_RANDOM
            );
        }

        // WireGuard
        $privateKey = null;
        if (null === $publicKey) {
            $privateKey = self::generatePrivateKey();
            $publicKey = self::extractPublicKey($privateKey);
        }

        [$ipFour, $ipSix] = $this->getIpAddress($profileConfig);

        $this->storage->wPeerAdd($userId, $profileId, $displayName, $publicKey, $ipFour, $ipSix, $expiresAt, $authKey);
        $this->vpnDaemon->wPeerAdd($profileConfig->nodeBaseUrl(), $publicKey, $ipFour, $ipSix);

        return new WgClientConfig(
            $profileConfig,
            $privateKey,
            $ipFour,
            $ipSix,
            $serverInfo->wgPublicKey(),
            $this->config->wgPort()
        );
    }

    public function disconnect(string $userId, string $profileId, string $connectionId): void
    {
        // XXX write proper log entries for all cases
        if (!$this->config->hasProfile($profileId)) {
            // profile does not exist (anymore)
            return;
        }
        $profileConfig = $this->config->profileConfig($profileId);

        if ('openvpn' === $profileConfig->vpnProto()) {
            $this->storage->oCertDelete($userId, $connectionId);
            $this->vpnDaemon->oDisconnectClient($profileConfig->nodeBaseUrl(), $connectionId);

            return;
        }

        $this->storage->wPeerRemove($userId, $connectionId);
        $this->vpnDaemon->wPeerRemove($profileConfig->nodeBaseUrl(), $connectionId);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function getIpAddress(ProfileConfig $profileConfig): array
    {
        // make a list of all allocated IPv4 addresses (the IPv6 address is
        // based on the IPv4 address)
        $allocatedIpFourList = $this->storage->wgGetAllocatedIpFourAddresses();
        $ipFourInRangeList = IP::fromIpPrefix($profileConfig->range())->clientIpList();
        $ipSixInRangeList = IP::fromIpPrefix($profileConfig->range6())->clientIpList(\count($ipFourInRangeList));
        foreach ($ipFourInRangeList as $k => $ipFourInRange) {
            if (!\in_array($ipFourInRange, $allocatedIpFourList, true)) {
                return [$ipFourInRange, $ipSixInRangeList[$k]];
            }
        }

        throw new ConnectionManagerException('no free IP address');
    }

    private static function generatePrivateKey(): string
    {
        ob_start();
        passthru('/usr/bin/wg genkey');

        return trim(ob_get_clean());
    }

    private static function extractPublicKey(string $privateKey): string
    {
        ob_start();
        passthru("echo {$privateKey} | /usr/bin/wg pubkey");

        return trim(ob_get_clean());
    }
}
