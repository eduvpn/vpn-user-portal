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
use LC\Portal\HttpClient\HttpClientInterface;
use LC\Portal\OpenVpn\ClientConfig;
use LC\Portal\WireGuard\WgClientConfig;

/**
 * List, add and remove connections.
 * XXX:
 * - create a separate VpnDaemon class that abstracts all HTTP request/responses
 *   and verifies/restructures them.
 */
class ConnectionManager
{
    protected RandomInterface $random;
    private Config $config;
    private Storage $storage;
    private HttpClientInterface $httpClient;

    public function __construct(Config $config, HttpClientInterface $httpClient, Storage $storage)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->httpClient = $httpClient;
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

        $daemonCache = [
            'o' => [],
            'w' => [],
        ];

        foreach ($this->config->profileConfigList() as $profileConfig) {
            $profileId = $profileConfig->profileId();
            $connectionList[$profileId] = [];

            if ('openvpn' === $profileConfig->vpnProto()) {
                // OpenVPN
                $certificateList = $this->storage->oCertListByProfileId($profileId);
                // XXX error handling

                if (\array_key_exists($profileConfig->nodeBaseUrl(), $daemonCache['o'])) {
                    $daemonConnectionList = $daemonCache['o'][$profileConfig->nodeBaseUrl()];
                } else {
                    $daemonConnectionList = Json::decode(
                        $this->httpClient->get($profileConfig->nodeBaseUrl().'/o/connection_list')->body()
                    );
                    $daemonCache['o'][$profileConfig->nodeBaseUrl()] = $daemonConnectionList;
                }

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
            // XXX error handling

            if (\array_key_exists($profileConfig->nodeBaseUrl(), $daemonCache['w'])) {
                $daemonWgPeerList = $daemonCache['w'][$profileConfig->nodeBaseUrl()];
            } else {
                $daemonWgPeerList = Json::decode(
                    $this->httpClient->get($profileConfig->nodeBaseUrl().'/w/peer_list', ['show_all' => $showAll ? 'yes' : 'no'])->body()
                );
                $daemonCache['w'][$profileConfig->nodeBaseUrl()] = $daemonWgPeerList;
            }

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

        // store peer in the DB
        $this->storage->wPeerAdd($userId, $profileId, $displayName, $publicKey, $ipFour, $ipSix, $expiresAt, $authKey);

        // add peer to WG
        // XXX make sure the public key config is overriden if the public key already exists
        $this->httpClient->post(
            $profileConfig->nodeBaseUrl().'/w/add_peer',
            [],
            [
                'public_key' => $publicKey,
                'ip_net' => [$ipFour.'/32', $ipSix.'/128'],
            ]
        );

        return new WgClientConfig(
            $profileConfig,
            $privateKey,
            $ipFour,
            $ipSix,
            $serverInfo->wgPublicKey(), // XXX server public key
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
            $this->httpClient->post($profileConfig->nodeBaseUrl().'/o/disconnect_client', [], ['common_name' => $connectionId]);

            return;
        }

        // WireGuard
        $this->storage->wPeerRemove($userId, $connectionId);
        $this->httpClient->post($profileConfig->nodeBaseUrl().'/w/remove_peer', [], ['public_key' => $connectionId]);
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
