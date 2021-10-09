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
use LC\Portal\OpenVpn\ClientConfig as OpenVpnClientConfig;
use LC\Portal\WireGuard\ClientConfig as WireGuardClientConfig;
use LC\Portal\WireGuard\KeyPair;

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
    public function get(): array
    {
        $connectionList = [];
        // keep the record of all nodeUrls we talked to so we only hit them
        // once... multiple profiles can have the same nodeUrl if the run
        // on the same machine/VM
        foreach ($this->config->profileConfigList() as $profileConfig) {
            $profileId = $profileConfig->profileId();
            $connectionList[$profileId] = [];

            if ('openvpn' === $profileConfig->vpnProto()) {
                // OpenVPN
                $oCertListByProfileId = $this->storage->oCertListByProfileId($profileId, Storage::INCLUDE_EXPIRED);

                $oConnectionList = [];
                for ($i = 0; $i < $profileConfig->nodeCount(); ++$i) {
                    // XXX foreach over nodeUrls?
                    $oConnectionList = array_merge($oConnectionList, $this->vpnDaemon->oConnectionList($profileConfig->nodeUrl($i)));
                }

                foreach ($oCertListByProfileId as $certInfo) {
                    if (\array_key_exists($certInfo['common_name'], $oConnectionList)) {
                        // found it!
                        $commonName = $certInfo['common_name'];
                        $connectionList[$profileId][] = [
                            'user_id' => $certInfo['user_id'],
                            'connection_id' => $certInfo['common_name'],
                            'display_name' => $certInfo['display_name'],
                            'ip_list' => [$oConnectionList[$commonName]['ip_four'], $oConnectionList[$commonName]['ip_six']],
                        ];
                    }
                }

                continue;
            }

            // WireGuard
            $wPeerListByProfileId = $this->storage->wPeerListByProfileId($profileId, Storage::INCLUDE_EXPIRED);
            $wPeerList = [];
            for ($i = 0; $i < $profileConfig->nodeCount(); ++$i) {
                // XXX foreach over nodeUrls?
                $wPeerList = array_merge($wPeerList, $this->vpnDaemon->wPeerList($profileConfig->nodeUrl($i), false));
            }

            foreach ($wPeerListByProfileId as $peerInfo) {
                if (\array_key_exists($peerInfo['public_key'], $wPeerList)) {
                    // found it!
                    $connectionList[$profileId][] = [
                        'user_id' => $peerInfo['user_id'],
                        'connection_id' => $peerInfo['public_key'],
                        'display_name' => $peerInfo['display_name'],
                        'ip_list' => [$peerInfo['ip_four'], $peerInfo['ip_six']],
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

        // Connect to a random node...
        // XXX we should probably detect whether a node is up, that is a much
        // better approach than taking random node! need to extend the daemon
        // to give more info on how it is used so we can direct traffic better
        // XXX also maybe implement "weight" based on available IP space or
        // about % in use by querying node?
        $nodeNumber = random_int(0, $profileConfig->nodeCount() - 1);

        if ('openvpn' === $profileConfig->vpnProto()) {
            $commonName = Base64::encode($this->random->get(32));
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
            return new OpenVpnClientConfig(
                $nodeNumber,
                $profileConfig,
                $serverInfo->ca()->caCert(),
                $serverInfo->tlsCrypt(),
                $certInfo,
                $tcpOnly,
                OpenVpnClientConfig::STRATEGY_RANDOM
            );
        }

        // WireGuard
        $privateKey = null;
        if (null === $publicKey) {
            $keyPair = KeyPair::generate();
            $privateKey = $keyPair['secret_key'];
            $publicKey = $keyPair['public_key'];
        }

        [$ipFour, $ipSix] = $this->getIpAddress($profileConfig, $nodeNumber);

        $this->storage->wPeerAdd($userId, $profileId, $displayName, $publicKey, $ipFour, $ipSix, $expiresAt, $authKey);
        $this->vpnDaemon->wPeerAdd($profileConfig->nodeUrl($nodeNumber), $publicKey, $ipFour, $ipSix);

        return new WireGuardClientConfig(
            $nodeNumber,
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
        // XXX how do we figure out the nodeNumber? We may need to store this
        // somehwere, or loop over all nodeUrls...
        // XXX this MUST be changed to diconnect from the proper node!
        $nodeNumber = 0;

        if (!$this->config->hasProfile($profileId)) {
            // profile does not exist (anymore)
            return;
        }
        $profileConfig = $this->config->profileConfig($profileId);

        if ('openvpn' === $profileConfig->vpnProto()) {
            $this->storage->oCertDelete($userId, $connectionId);
            $this->vpnDaemon->oDisconnectClient($profileConfig->nodeUrl($nodeNumber), $connectionId);

            return;
        }

        $this->storage->wPeerRemove($userId, $connectionId);
        $this->vpnDaemon->wPeerRemove($profileConfig->nodeUrl($nodeNumber), $connectionId);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function getIpAddress(ProfileConfig $profileConfig, int $nodeNumber): array
    {
        // make a list of all allocated IPv4 addresses (the IPv6 address is
        // based on the IPv4 address)
        $allocatedIpFourList = $this->storage->wgGetAllocatedIpFourAddresses();
        $ipFourInRangeList = $profileConfig->range($nodeNumber)->clientIpList();
        $ipSixInRangeList = $profileConfig->range6($nodeNumber)->clientIpList(\count($ipFourInRangeList));
        foreach ($ipFourInRangeList as $k => $ipFourInRange) {
            if (!\in_array($ipFourInRange, $allocatedIpFourList, true)) {
                return [$ipFourInRange, $ipSixInRangeList[$k]];
            }
        }

        throw new ConnectionManagerException('no free IP address');
    }
}
