<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use DateInterval;
use DateTimeImmutable;
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\Cfg\ProfileConfig;
use Vpn\Portal\Exception\ConnectionManagerException;
use Vpn\Portal\OpenVpn\ClientConfig as OpenVpnClientConfig;
use Vpn\Portal\WireGuard\ClientConfig as WireGuardClientConfig;

/**
 * List, add and remove connections.
 */
class ConnectionManager
{
    protected DateTimeImmutable $dateTime;
    private Config $config;
    private VpnDaemon $vpnDaemon;
    private Storage $storage;
    private ConnectionHookInterface $connectionHook;
    private LoggerInterface $logger;

    public function __construct(Config $config, VpnDaemon $vpnDaemon, Storage $storage, ConnectionHookInterface $connectionHook, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->vpnDaemon = $vpnDaemon;
        $this->storage = $storage;
        $this->connectionHook = $connectionHook;
        $this->logger = $logger;
        $this->dateTime = Dt::get();
    }

    /**
     * @return array<array{node_number:int,node_url:string,node_info:?array{rel_load_average:array<int>,load_average:array<float>,cpu_count:int,node_uptime:int}}>
     */
    public function nodeInfo(): array
    {
        $nodeInfoList = [];
        foreach ($this->config->nodeNumberUrlList() as $nodeNumber => $nodeUrl) {
            $nodeInfoList[] = [
                'node_number' => $nodeNumber,
                'node_url' => $nodeUrl,
                'node_info' => $this->vpnDaemon->nodeInfo($nodeUrl),
            ];
        }

        return $nodeInfoList;
    }

    /**
     * Retrieve a list of all current OpenVPN and WireGuard connections, sorted
     * by profile.
     *
     * XXX: loop over connections instead of database row, as there are
     * typically way less connections than entries in the database
     *
     * @return array<string,array<array{user_id:string,connection_id:string,display_name:string,ip_list:array<string>,vpn_proto:string,auth_key:?string}>>
     */
    public function get(): array
    {
        $wPeerListFromDb = $this->storage->wPeerList();
        $oCertListFromDb = $this->storage->oCertList();

        // retrieve a list of all active OpenVPN and WireGuard connections from
        // all daemons
        $wPeerListFromDaemon = [];
        $oCertListFromDaemon = [];
        foreach ($this->config->nodeNumberUrlList() as $nodeUrl) {
            $wPeerListFromDaemon = array_merge($wPeerListFromDaemon, $this->vpnDaemon->wPeerList($nodeUrl, false));
            $oCertListFromDaemon = array_merge($oCertListFromDaemon, $this->vpnDaemon->oConnectionList($nodeUrl));
        }

        $connectionList = [];
        // we need to create an empty structure here with all profiles,
        // otherwise the "Connections" admin page does not show all profiles
        // XXX figure out if we can be smarter here
        foreach ($this->config->profileConfigList() as $profileConfig) {
            $connectionList[$profileConfig->profileId()] = [];
        }

        // WireGuard
        foreach ($wPeerListFromDb as $peerInfo) {
            $publicKey = $peerInfo['public_key'];
            $profileId = $peerInfo['profile_id'];
            if (\array_key_exists($publicKey, $wPeerListFromDaemon)) {
                $connectionList[$profileId][] = [
                    'user_id' => $peerInfo['user_id'],
                    'connection_id' => $publicKey,
                    'display_name' => $peerInfo['display_name'],
                    'ip_list' => [$peerInfo['ip_four'], $peerInfo['ip_six']],
                    'vpn_proto' => 'wireguard',
                    'auth_key' => $peerInfo['auth_key'],
                ];
            }
        }

        // OpenVPN
        foreach ($oCertListFromDb as $certInfo) {
            $commonName = $certInfo['common_name'];
            $profileId = $certInfo['profile_id'];
            if (\array_key_exists($commonName, $oCertListFromDaemon)) {
                $connectionList[$profileId][] = [
                    'user_id' => $certInfo['user_id'],
                    'connection_id' => $commonName,
                    'display_name' => $certInfo['display_name'],
                    'ip_list' => [$oCertListFromDaemon[$commonName]['ip_four'], $oCertListFromDaemon[$commonName]['ip_six']],
                    'vpn_proto' => 'openvpn',
                    'auth_key' => $certInfo['auth_key'],
                ];
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
        foreach ($this->storage->oCertInfoListByAuthKey($authKey) as $oCertInfo) {
            $this->oDisconnect(
                $oCertInfo['profile_id'],
                $oCertInfo['node_number'],
                $oCertInfo['common_name']
            );
        }

        foreach ($this->storage->wPeerInfoListByAuthKey($authKey) as $wPeerInfo) {
            $this->wDisconnect(
                $wPeerInfo['user_id'],
                $wPeerInfo['profile_id'],
                $wPeerInfo['node_number'],
                $wPeerInfo['public_key'],
                $wPeerInfo['ip_four'],
                $wPeerInfo['ip_six']
            );
        }
    }

    public function disconnectByUserId(string $userId): void
    {
        foreach ($this->storage->oCertInfoListByUserId($userId) as $oCertInfo) {
            $this->oDisconnect(
                $oCertInfo['profile_id'],
                $oCertInfo['node_number'],
                $oCertInfo['common_name']
            );
        }

        foreach ($this->storage->wPeerInfoListByUserId($userId) as $wPeerInfo) {
            $this->wDisconnect(
                $userId,
                $wPeerInfo['profile_id'],
                $wPeerInfo['node_number'],
                $wPeerInfo['public_key'],
                $wPeerInfo['ip_four'],
                $wPeerInfo['ip_six']
            );
        }
    }

    public function disconnectByConnectionId(string $userId, string $connectionId): void
    {
        if (null !== $wPeerInfo = $this->storage->wPeerInfo($connectionId)) {
            if ($userId !== $wPeerInfo['user_id']) {
                // connectionId does not belong to user
                return;
            }

            $this->wDisconnect(
                $wPeerInfo['user_id'],
                $wPeerInfo['profile_id'],
                $wPeerInfo['node_number'],
                $connectionId,
                $wPeerInfo['ip_four'],
                $wPeerInfo['ip_six']
            );

            return;
        }

        if (null !== $oCertInfo = $this->storage->oCertInfo($connectionId)) {
            if ($userId !== $oCertInfo['user_id']) {
                // connectionId does not belong to user
                return;
            }
            $this->oDisconnect(
                $oCertInfo['profile_id'],
                $oCertInfo['node_number'],
                $connectionId
            );

            return;
        }

        $this->logger->warning(sprintf('unable to find public key or common name "%s"', $connectionId));
    }

    /**
     * @param array{wireguard:bool,openvpn:bool} $clientProtoSupport
     */
    public function connect(ServerInfo $serverInfo, ProfileConfig $profileConfig, string $userId, array $clientProtoSupport, string $displayName, DateTimeImmutable $expiresAt, bool $preferTcp, ?string $publicKey, ?string $authKey): ClientConfigInterface
    {
        foreach (Protocol::determine($profileConfig, $clientProtoSupport, $publicKey, $preferTcp) as $vpnProto) {
            switch ($vpnProto) {
                case 'wireguard':
                    /**
                     * Protocol::determine makes sure $publicKey is NOT null.
                     *
                     * @var string $publicKey
                     */
                    if (null === $wireGuardConfig = $this->wConnect($serverInfo, $profileConfig, $userId, $displayName, $expiresAt, $publicKey, $authKey)) {
                        break;
                    }

                    return $wireGuardConfig;
                case 'openvpn':
                    if (null === $openVpnConfig = $this->oConnect($serverInfo, $profileConfig, $userId, $displayName, $expiresAt, $preferTcp, $authKey)) {
                        break;
                    }

                    return $openVpnConfig;
            }
        }

        throw new ConnectionManagerException('unable to connect using any of the common supported VPN protocols');
    }

    /**
     * Remove all peers and certificates from the database that should no
     * longer be there.
     */
    public function cleanDb(): void
    {
        $this->wRemoveInvalidPeersDb();
        $this->oDeleteInvalidCertsDb();
    }

    /**
     * Synchronize the state between the database and the VPN daemon.
     */
    public function sync(): void
    {
        $this->oSync();
        $this->wSync();
    }

    protected function getRandomBytes(): string
    {
        return random_bytes(32);
    }

    /**
     * Filter all OpenVPN certificates in the database and ONLY return the ones
     * that should be allowed to connect.
     *
     * (1) User MUST NOT be disabled
     * (2) Certificate MUST NOT have expired
     * (3) Profile MUST still exist
     * (4) Node MUST still exist
     *
     * @return array<string, array{auth_key: null|string, common_name: string, created_at: DateTimeImmutable, display_name: string, expires_at: DateTimeImmutable, node_number: int, profile_id: string, user_id: string}>
     */
    private function oFilterDb(): array
    {
        $oCertList = $this->storage->oCertList();
        $oFilteredCertList = [];
        foreach ($oCertList as $commonName => $oCertInfo) {
            if ($oCertInfo['user_is_disabled']) {
                continue;
            }
            unset($oCertInfo['user_is_disabled']);
            if ($oCertInfo['expires_at'] <= $this->dateTime) {
                continue;
            }
            $profileId = $oCertInfo['profile_id'];
            if (!$this->config->hasProfile($profileId)) {
                continue;
            }
            $profileConfig = $this->config->profileConfig($profileId);
            $nodeNumber = $oCertInfo['node_number'];
            if (!in_array($nodeNumber, $profileConfig->onNode(), true)) {
                continue;
            }
            $oFilteredCertList[$commonName] = $oCertInfo;
        }

        return $oFilteredCertList;
    }

    /**
     * Filter all WireGuard peers in the database and ONLY return the ones
     * that should be allowed to connect.
     *
     * (1) User MUST NOT be disabled
     * (2) Peer MUST NOT have expired
     * (3) Profile MUST still exist
     * (4) Node MUST still exist
     * (5) IPv4 address MUST belong to prefix of Profile (+Node)
     * (6) IPv6 address MUST belong to prefix of Profile (+Node)
     *
     * @return array<string, array{auth_key: null|string, created_at: DateTimeImmutable, display_name: string, expires_at: DateTimeImmutable, ip_four: string, ip_six: string, node_number: int, profile_id: string, public_key: string, user_id: string}>
     */
    private function wFilterDb(): array
    {
        $wPeerList = $this->storage->wPeerList();
        $wFilteredPeerList = [];
        foreach ($wPeerList as $publicKey => $wPeerInfo) {
            if ($wPeerInfo['user_is_disabled']) {
                continue;
            }
            unset($wPeerInfo['user_is_disabled']);
            if ($wPeerInfo['expires_at'] <= $this->dateTime) {
                continue;
            }
            $profileId = $wPeerInfo['profile_id'];
            if (!$this->config->hasProfile($profileId)) {
                continue;
            }
            $profileConfig = $this->config->profileConfig($profileId);
            $nodeNumber = $wPeerInfo['node_number'];
            if (!in_array($nodeNumber, $profileConfig->onNode(), true)) {
                continue;
            }
            $ipFour = Ip::fromIp($wPeerInfo['ip_four']);
            if (!$profileConfig->wRangeFour($nodeNumber)->contains($ipFour)) {
                continue;
            }
            $ipSix = Ip::fromIp($wPeerInfo['ip_six']);
            if (!$profileConfig->wRangeSix($nodeNumber)->contains($ipSix)) {
                continue;
            }

            $wFilteredPeerList[$publicKey] = $wPeerInfo;
        }

        return $wFilteredPeerList;
    }

    private function oSync(): void
    {
        $oCertListFromDb = $this->oFilterDb();
        foreach ($this->config->nodeNumberUrlList() as $nodeUrl) {
            foreach (array_keys($this->vpnDaemon->oConnectionList($nodeUrl)) as $commonName) {
                if (!\array_key_exists($commonName, $oCertListFromDb)) {
                    $this->logger->debug(sprintf('%s: disconnecting client with common name "%s"', __METHOD__, $commonName));
                    $this->vpnDaemon->oDisconnectClient($nodeUrl, $commonName);
                }
            }
        }
    }

    private function wSync(): void
    {
        $appGoneInterval = $this->config->apiConfig()->appGoneInterval();
        $appGoneCutoffMoment = $this->dateTime->sub($appGoneInterval);

        $wPeerListFromDb = $this->wFilterDb();
        $connectedPublicKeyList = [];
        foreach ($this->config->nodeNumberUrlList() as $nodeNumber => $nodeUrl) {
            $nodeUptime = new DateInterval('PT0S');
            if (null !== $nodeInfo = $this->vpnDaemon->nodeInfo($nodeUrl)) {
                $nodeUptime = new DateInterval('PT'.$nodeInfo['node_uptime'].'S');
            }
            // we require the node to be up at least "appGoneInterval" before
            // checking whether API clients are "gone"
            $nodeHasAdequateUptime = $this->dateTime->sub($nodeUptime)->add($appGoneInterval) < $this->dateTime;
            foreach ($this->vpnDaemon->wPeerList($nodeUrl, true) as $publicKey => $wPeerInfo) {
                if (!\array_key_exists($publicKey, $wPeerListFromDb)) {
                    $this->wSyncDisconnect($nodeNumber, $publicKey, 'public key not in database');

                    continue;
                }
                if ($nodeHasAdequateUptime) {
                    $wPeerDbInfo = $wPeerListFromDb[$publicKey];
                    if (null !== $wPeerDbInfo['auth_key']) {
                        // WireGuard configuration was issued to an API client
                        if ($wPeerDbInfo['created_at'] < $appGoneCutoffMoment) {
                            // configuration must have been created before the
                            // cutoff
                            if (null === $lastHandshakeTime = $wPeerInfo['last_handshake_time']) {
                                $this->wSyncDisconnect($nodeNumber, $publicKey, 'no handshake since node boot');
                                // make sure it does not get added again in
                                // next section
                                unset($wPeerListFromDb[$publicKey]);

                                continue;
                            }
                            if (new DateTimeImmutable($lastHandshakeTime) < $appGoneCutoffMoment) {
                                $this->wSyncDisconnect($nodeNumber, $publicKey, 'handshake too long ago');
                                // make sure it does not get added again in
                                // next section
                                unset($wPeerListFromDb[$publicKey]);

                                continue;
                            }
                        }
                    }
                }

                $connectedPublicKeyList[] = $publicKey;
            }
        }

        foreach ($wPeerListFromDb as $publicKey => $wPeerInfo) {
            if (in_array($publicKey, $connectedPublicKeyList, true)) {
                continue;
            }
            $this->logger->debug(sprintf('%s: adding peer [%s,%d,%s]', __METHOD__, $wPeerInfo['profile_id'], $wPeerInfo['node_number'], $publicKey));
            $this->vpnDaemon->wPeerAdd(
                $this->config->profileConfig($wPeerInfo['profile_id'])->nodeUrl($wPeerInfo['node_number']),
                $publicKey,
                $wPeerInfo['ip_four'],
                $wPeerInfo['ip_six']
            );
        }
    }

    /**
     * Remove/disconnect a peer during "sync" stage based on various criteria.
     *
     * @see ConnectionManager::wSync()
     */
    private function wSyncDisconnect(int $nodeNumber, string $publicKey, string $disconnectReason): void
    {
        if (null !== $openConnectionInfo = $this->storage->openConnectionInfo($publicKey)) {
            $this->logger->debug(sprintf('%s: removing peer (reason: %s) [%s,%d,%s]', __METHOD__, $disconnectReason, $openConnectionInfo['profile_id'], $nodeNumber, $publicKey));
            $this->wDisconnect($openConnectionInfo['user_id'], $openConnectionInfo['profile_id'], $nodeNumber, $publicKey, $openConnectionInfo['ip_four'], $openConnectionInfo['ip_six']);
        }
        // XXX should we log when there is no open connection for this public key in the connection_log table?
    }

    private function wDisconnect(string $userId, string $profileId, int $nodeNumber, string $publicKey, string $ipFour, string $ipSix): void
    {
        if (null === $nodeUrl = $this->nodeUrl($nodeNumber)) {
            $this->logger->warning(sprintf('node "%d" does not exist (anymore)', $nodeNumber));

            return;
        }

        $this->storage->wPeerRemove($publicKey);
        $bytesIn = 0;
        $bytesOut = 0;
        if (null !== $daemonPeerInfo = $this->vpnDaemon->wPeerRemove($nodeUrl, $publicKey)) {
            $bytesIn = $daemonPeerInfo['bytes_in'];
            $bytesOut = $daemonPeerInfo['bytes_out'];
        }

        $this->connectionHook->disconnect($userId, $profileId, 'wireguard', $publicKey, $ipFour, $ipSix, $bytesIn, $bytesOut);
    }

    private function oDisconnect(string $profileId, int $nodeNumber, string $commonName): void
    {
        if (null === $nodeUrl = $this->nodeUrl($nodeNumber)) {
            $this->logger->warning(sprintf('node "%d" does not exist (anymore)', $nodeNumber));

            return;
        }

        $this->storage->oCertDelete($commonName);
        $this->vpnDaemon->oDisconnectClient($nodeUrl, $commonName);
    }

    private function wConnect(ServerInfo $serverInfo, ProfileConfig $profileConfig, string $userId, string $displayName, DateTimeImmutable $expiresAt, string $publicKey, ?string $authKey): ?WireGuardClientConfig
    {
        $nodeNumberList = $profileConfig->onNode();
        shuffle($nodeNumberList);

        foreach ($nodeNumberList as $nodeNumber) {
            if (null === $this->vpnDaemon->nodeInfo($profileConfig->nodeUrl($nodeNumber))) {
                // node not available, try next
                $this->logger->error(sprintf('node "%d" (%s) is not available', $nodeNumber, $profileConfig->nodeUrl($nodeNumber)));

                continue;
            }
            if (null === $serverPublicKey = $serverInfo->publicKey($nodeNumber)) {
                // node's public key not known, try next
                $this->logger->error(sprintf('public key of node "%d" (%s) is not set', $nodeNumber, $profileConfig->nodeUrl($nodeNumber)));

                continue;
            }
            [$ipFour, $ipSix, $err] = $this->getIpAddress($profileConfig, $nodeNumber);
            if (null !== $err) {
                // no free IP address available on this node, try next
                $this->logger->warning(sprintf('no free IP address available on node "%d" (%s)', $nodeNumber, $profileConfig->nodeUrl($nodeNumber)));

                continue;
            }

            $this->storage->wPeerAdd($userId, $nodeNumber, $profileConfig->profileId(), $displayName, $publicKey, $ipFour, $ipSix, $this->dateTime, $expiresAt, $authKey);
            $this->vpnDaemon->wPeerAdd($profileConfig->nodeUrl($nodeNumber), $publicKey, $ipFour, $ipSix);
            $this->connectionHook->connect($userId, $profileConfig->profileId(), 'wireguard', $publicKey, $ipFour, $ipSix, null);

            return new WireGuardClientConfig(
                $serverInfo->portalUrl(),
                $nodeNumber,
                $profileConfig,
                $ipFour,
                $ipSix,
                $serverPublicKey,
                $this->config->wireGuardConfig(),
                $expiresAt
            );
        }

        // we found no suitable node to connect to...
        $this->logger->error(sprintf('unable to find a suitable node to connect to for profile "%s"', $profileConfig->profileId()));

        return null;
    }

    private function oConnect(ServerInfo $serverInfo, ProfileConfig $profileConfig, string $userId, string $displayName, DateTimeImmutable $expiresAt, bool $preferTcp, ?string $authKey): ?OpenVpnClientConfig
    {
        $nodeNumberList = $profileConfig->onNode();
        shuffle($nodeNumberList);

        foreach ($nodeNumberList as $nodeNumber) {
            if (null === $this->vpnDaemon->nodeInfo($profileConfig->nodeUrl($nodeNumber))) {
                // node not available, try next
                $this->logger->error(sprintf('node "%d" (%s) is not available', $nodeNumber, $profileConfig->nodeUrl($nodeNumber)));

                continue;
            }

            $commonName = Base64::encode($this->getRandomBytes());
            $certInfo = $serverInfo->ca()->clientCert($commonName, $profileConfig->profileId(), $expiresAt);
            $this->storage->oCertAdd(
                $userId,
                $nodeNumber,
                $profileConfig->profileId(),
                $commonName,
                $displayName,
                $this->dateTime,
                $expiresAt,
                $authKey
            );

            // this thing can throw an ClientConfigException!
            return new OpenVpnClientConfig(
                $serverInfo->portalUrl(),
                $nodeNumber,
                $profileConfig,
                $serverInfo->ca()->caCert(),
                $serverInfo->tlsCrypt(),
                $certInfo,
                $preferTcp,
                $expiresAt
            );
        }

        // we found no suitable node to connect to...
        $this->logger->error(sprintf('unable to find a suitable node to connect to for profile "%s"', $profileConfig->profileId()));

        return null;
    }

    /**
     * Remove all WireGuard peers from the DB that should no longer be there.
     */
    private function wRemoveInvalidPeersDb(): void
    {
        $wPeerList = $this->storage->wPeerList();
        $wFilteredPeerList = $this->wFilterDb();
        foreach ($wPeerList as $publicKey => $wPeerInfo) {
            if (!array_key_exists($publicKey, $wFilteredPeerList)) {
                $this->logger->debug(sprintf('%s: removing peer with public key "%s" from database', __METHOD__, $publicKey));
                $this->storage->wPeerRemove($publicKey);
            }
        }
    }

    /**
     * Delete all OpenVPN certificates from the DB that should no longer be
     * there.
     */
    private function oDeleteInvalidCertsDb(): void
    {
        $oCertList = $this->storage->oCertList();
        $oFilteredCertList = $this->oFilterDb();
        foreach ($oCertList as $commonName => $oCertInfo) {
            if (!array_key_exists($commonName, $oFilteredCertList)) {
                $this->logger->debug(sprintf('%s: deleting client with common name "%s" from database', __METHOD__, $commonName));
                $this->storage->oCertDelete($commonName);
            }
        }
    }

    private function nodeUrl(int $nodeNumber): ?string
    {
        $nodeNumberUrlList = $this->config->nodeNumberUrlList();
        if (array_key_exists($nodeNumber, $nodeNumberUrlList)) {
            return $nodeNumberUrlList[$nodeNumber];
        }

        return null;
    }

    /**
     * Get a free IPv4 and IPv6 address for a specific node belonging to a
     * profile.
     *
     * @return array{0:string,1:string,2:?string}
     */
    private function getIpAddress(ProfileConfig $profileConfig, int $nodeNumber): array
    {
        // make a list of all allocated IPv4 addresses (the IPv6 address is
        // based on the IPv4 address)
        $wAllocatedIpFourList = $this->storage->wAllocatedIpFourList($profileConfig->profileId(), $nodeNumber);
        $ipFourInRangeList = $profileConfig->wRangeFour($nodeNumber)->clientIpListFour();
        $ipSixInRangeList = $profileConfig->wRangeSix($nodeNumber)->clientIpListSix(\count($ipFourInRangeList));
        foreach ($ipFourInRangeList as $k => $ipFourInRange) {
            if (!\in_array($ipFourInRange, $wAllocatedIpFourList, true)) {
                return [$ipFourInRange, $ipSixInRangeList[$k], null];
            }
        }

        return ['', '', 'unable to find a free IP address'];
    }
}
