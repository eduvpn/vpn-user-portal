<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use DateTimeImmutable;
use RangeException;
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
                $oCertInfo['user_id'],
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
                $userId,
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

    public function disconnectByConnectionId(string $connectionId): void
    {
        if (null !== $wPeerInfo = $this->storage->wPeerInfo($connectionId)) {
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
            $this->oDisconnect(
                $oCertInfo['user_id'],
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
     *
     * @throws \Vpn\Portal\Exception\ProtocolException
     * @throws \Vpn\Portal\Exception\ConnectionManagerException
     */
    public function connect(ServerInfo $serverInfo, ProfileConfig $profileConfig, string $userId, array $clientProtoSupport, string $displayName, DateTimeImmutable $expiresAt, bool $preferTcp, ?string $publicKey, ?string $authKey): ClientConfigInterface
    {
        $useProto = Protocol::determine($profileConfig, $clientProtoSupport, $publicKey, $preferTcp);
        $nodeNumber = $this->randomNodeNumber($profileConfig);

        switch ($useProto) {
            case 'openvpn':
                return $this->oConnect($serverInfo, $profileConfig, $nodeNumber, $userId, $displayName, $expiresAt, $preferTcp, $authKey);

            case 'wireguard':
                // Protocol::determine makes sure a public key was sent by the client, but just in case
                if (null === $publicKey) {
                    throw new ConnectionManagerException('unable to connect using wireguard, no public key provided by client');
                }
                if (null !== $wireGuardClientConfig = $this->wConnect($serverInfo, $profileConfig, $nodeNumber, $userId, $displayName, $expiresAt, $publicKey, $authKey)) {
                    return $wireGuardClientConfig;
                }

                // we were unable to find a free IP address for WireGuard,
                // fallback to OpenVPN if client & protocol support it
                if ($clientProtoSupport['openvpn'] && $profileConfig->oSupport()) {
                    return $this->oConnect($serverInfo, $profileConfig, $nodeNumber, $userId, $displayName, $expiresAt, $preferTcp, $authKey);
                }

                throw new ConnectionManagerException('unable to connect using wireguard, openvpn fallback not possible');

            default:
                throw new RangeException('invalid protocol');
        }
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
     * @return array<string,array{node_number:int,user_id:string,profile_id:string,display_name:string,common_name:string,expires_at:\DateTimeImmutable,auth_key:?string,user_is_disabled:bool}>
     */
    private function oFilterDb(): array
    {
        $oCertList = $this->storage->oCertList();
        $oFilteredCertList = [];
        foreach ($oCertList as $commonName => $oCertInfo) {
            if ($oCertInfo['user_is_disabled']) {
                continue;
            }
            if ($oCertInfo['expires_at'] <= $this->dateTime) {
                continue;
            }
            $profileId = $oCertInfo['profile_id'];
            if (!$this->config->hasProfile($profileId)) {
                continue;
            }
            $profileConfig = $this->config->profileConfig($profileId);
            $nodeNumber = $oCertInfo['node_number'];
            if (!in_array($nodeNumber, $profileConfig->onNode())) {
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
     * @return array<string,array{node_number:int,user_id:string,profile_id:string,display_name:string,public_key:string,ip_four:string,ip_six:string,expires_at:\DateTimeImmutable,auth_key:?string,user_is_disabled:bool}>
     */
    private function wFilterDb(): array
    {
        $wPeerList = $this->storage->wPeerList();
        $wFilteredPeerList = [];
        foreach ($wPeerList as $publicKey => $wPeerInfo) {
            if ($wPeerInfo['user_is_disabled']) {
                continue;
            }
            if ($wPeerInfo['expires_at'] <= $this->dateTime) {
                continue;
            }
            $profileId = $wPeerInfo['profile_id'];
            if (!$this->config->hasProfile($profileId)) {
                continue;
            }
            $profileConfig = $this->config->profileConfig($profileId);
            $nodeNumber = $wPeerInfo['node_number'];
            if (!in_array($nodeNumber, $profileConfig->onNode())) {
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
        $wPeerListFromDb = $this->wFilterDb();
        $connectedPublicKeyList = [];
        foreach ($this->config->nodeNumberUrlList() as $nodeNumber => $nodeUrl) {
            foreach (array_keys($this->vpnDaemon->wPeerList($nodeUrl, true)) as $publicKey) {
                if (!\array_key_exists($publicKey, $wPeerListFromDb)) {
                    $this->logger->debug(sprintf('%s: unregistering peer with public key "%s"', __METHOD__, $publicKey));
                    if (null !== $openConnectionInfo = $this->storage->openConnectionInfo($publicKey)) {
                        $this->wDisconnect($openConnectionInfo['user_id'], $openConnectionInfo['profile_id'], $nodeNumber, $publicKey, $openConnectionInfo['ip_four'], $openConnectionInfo['ip_six']);
                    }
                    // XXX should we log when there is no open connection for this public key in the connection_log table?

                    continue;
                }
                $connectedPublicKeyList[] = $publicKey;
            }
        }

        foreach ($wPeerListFromDb as $publicKey => $wPeerInfo) {
            if (in_array($publicKey, $connectedPublicKeyList, true)) {
                continue;
            }
            $this->logger->debug(sprintf('%s: registering peer with public key "%s"', __METHOD__, $publicKey));
            $this->vpnDaemon->wPeerAdd(
                $this->config->profileConfig($wPeerInfo['profile_id'])->nodeUrl($wPeerInfo['node_number']),
                $publicKey,
                $wPeerInfo['ip_four'],
                $wPeerInfo['ip_six']
            );
        }
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

    private function oDisconnect(string $userId, string $profileId, int $nodeNumber, string $commonName): void
    {
        if (null === $nodeUrl = $this->nodeUrl($nodeNumber)) {
            $this->logger->warning(sprintf('node "%d" does not exist (anymore)', $nodeNumber));

            return;
        }

        $this->storage->oCertDelete($commonName);
        $this->vpnDaemon->oDisconnectClient($nodeUrl, $commonName);
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
     * Try to find a usable node to connect to, loop over all of them in a
     * random order until we find one that responds. This algorithm can use
     * some tweaking, e.g. consider the load of a node instead of just checking
     * whether it is up...
     */
    private function randomNodeNumber(ProfileConfig $profileConfig): int
    {
        $nodeList = $profileConfig->onNode();
        shuffle($nodeList);
        foreach ($nodeList as $nodeNumber) {
            if (null !== $this->vpnDaemon->nodeInfo($profileConfig->nodeUrl($nodeNumber))) {
                return $nodeNumber;
            }
            $this->logger->error(sprintf('VPN node "%d" running at (%s) is not available', $nodeNumber, $profileConfig->nodeUrl($nodeNumber)));
        }
        $this->logger->error('no VPN node available');

        throw new ConnectionManagerException('no VPN node available');
    }

    /**
     * @throws \Vpn\Portal\Exception\ConnectionManagerException
     */
    private function wConnect(ServerInfo $serverInfo, ProfileConfig $profileConfig, int $nodeNumber, string $userId, string $displayName, DateTimeImmutable $expiresAt, string $publicKey, ?string $authKey): ?WireGuardClientConfig
    {
        if (!$profileConfig->wSupport()) {
            throw new ConnectionManagerException('profile does not support wireguard');
        }

        // make sure the node registered their public key with us
        if (null === $serverPublicKey = $serverInfo->publicKey($nodeNumber)) {
            throw new ConnectionManagerException(sprintf('node "%d" did not yet register their WireGuard public key', $nodeNumber));
        }

        if (null === $ipInfo = $this->getIpAddress($profileConfig, $nodeNumber)) {
            // unable to find a free IP address, give up on this connection
            // attempt...
            return null;
        }
        $ipFour = $ipInfo['ip_four'];
        $ipSix = $ipInfo['ip_six'];

        // XXX we MUST make sure public_key is unique on this server!!!
        // the DB enforces this, but maybe a better error could be given?
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
            $this->config->wireGuardConfig()->listenPort(),
            $expiresAt
        );
    }

    private function oConnect(ServerInfo $serverInfo, ProfileConfig $profileConfig, int $nodeNumber, string $userId, string $displayName, DateTimeImmutable $expiresAt, bool $preferTcp, ?string $authKey): OpenVpnClientConfig
    {
        if (!$profileConfig->oSupport()) {
            throw new ConnectionManagerException('profile does not support openvpn');
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

    /**
     * @return ?array{ip_four:string,ip_six:string}
     */
    private function getIpAddress(ProfileConfig $profileConfig, int $nodeNumber): ?array
    {
        // make a list of all allocated IPv4 addresses (the IPv6 address is
        // based on the IPv4 address)
        $allocatedIpFourList = $this->storage->wgGetAllocatedIpFourAddresses($profileConfig->profileId(), $nodeNumber);
        $ipFourInRangeList = $profileConfig->wRangeFour($nodeNumber)->clientIpListFour();
        $ipSixInRangeList = $profileConfig->wRangeSix($nodeNumber)->clientIpListSix(\count($ipFourInRangeList));
        foreach ($ipFourInRangeList as $k => $ipFourInRange) {
            if (!\in_array($ipFourInRange, $allocatedIpFourList, true)) {
                return ['ip_four' => $ipFourInRange, 'ip_six' => $ipSixInRangeList[$k]];
            }
        }

        return null;
    }
}
