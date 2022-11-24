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
use Vpn\Portal\Exception\ConnectionHookException;
use Vpn\Portal\Exception\ConnectionManagerException;
use Vpn\Portal\OpenVpn\ClientConfig as OpenVpnClientConfig;
use Vpn\Portal\WireGuard\ClientConfig as WireGuardClientConfig;

/**
 * List, add and remove connections.
 */
class ConnectionManager
{
    /**
     * Used to indicate the certificate/peer should NOT be deleted, e.g. when
     * an account is disabled.
     */
    public const DO_NOT_DELETE = 1;

    protected DateTimeImmutable $dateTime;
    private Config $config;
    private Storage $storage;
    private VpnDaemon $vpnDaemon;
    private LoggerInterface $logger;

    /** @var array<ConnectionHookInterface> */
    private array $connectionHookList = [];

    public function __construct(Config $config, VpnDaemon $vpnDaemon, Storage $storage, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->vpnDaemon = $vpnDaemon;
        $this->logger = $logger;
        $this->dateTime = Dt::get();
    }

    public function addConnectionHook(ConnectionHookInterface $connectionHook): void
    {
        $this->connectionHookList[] = $connectionHook;
    }

    /**
     * @return array<string,array<array{user_id:string,connection_id:string,display_name:string,ip_list:array<string>,vpn_proto:string,auth_key:?string}>>
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

            if ($profileConfig->oSupport()) {
                // OpenVPN
                $oCertListByProfileId = $this->storage->oCertListByProfileId($profileId, Storage::INCLUDE_EXPIRED);

                $oConnectionList = [];
                foreach ($profileConfig->onNode() as $nodeNumber) {
                    $oConnectionList = array_merge($oConnectionList, $this->vpnDaemon->oConnectionList($profileConfig->nodeUrl($nodeNumber)));
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
                            'vpn_proto' => 'openvpn',
                            'auth_key' => $certInfo['auth_key'],
                        ];
                    }
                }
            }

            if ($profileConfig->wSupport()) {
                // WireGuard
                $wPeerListByProfileId = $this->storage->wPeerListByProfileId($profileId, Storage::INCLUDE_EXPIRED);
                $wPeerList = [];
                foreach ($profileConfig->onNode() as $nodeNumber) {
                    $wPeerList = array_merge($wPeerList, $this->vpnDaemon->wPeerList($profileConfig->nodeUrl($nodeNumber), false));
                }

                foreach ($wPeerListByProfileId as $peerInfo) {
                    if (\array_key_exists($peerInfo['public_key'], $wPeerList)) {
                        // found it!
                        $connectionList[$profileId][] = [
                            'user_id' => $peerInfo['user_id'],
                            'connection_id' => $peerInfo['public_key'],
                            'display_name' => $peerInfo['display_name'],
                            'ip_list' => [$peerInfo['ip_four'], $peerInfo['ip_six']],
                            'vpn_proto' => 'wireguard',
                            'auth_key' => $peerInfo['auth_key'],
                        ];
                    }
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

    public function disconnectByUserId(string $userId, int $optionFlags = 0): void
    {
        // OpenVPN
        foreach ($this->storage->oCertListByUserId($userId) as $oCertInfo) {
            $this->disconnect(
                $userId,
                $oCertInfo['profile_id'],
                $oCertInfo['common_name'],
                $optionFlags
            );
        }

        // WireGuard
        foreach ($this->storage->wPeerListByUserId($userId) as $wPeerInfo) {
            $this->disconnect(
                $userId,
                $wPeerInfo['profile_id'],
                $wPeerInfo['public_key'],
                $optionFlags
            );
        }
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

    public function disconnect(string $userId, string $profileId, string $connectionId, int $optionFlags = 0): void
    {
        [$vpnProto, $nodeNumber] = $this->determineVpnProtoNodeNumber($userId, $profileId, $connectionId);
        if (null === $vpnProto || null === $nodeNumber) {
            // can't find the connection, nothing we can do
            return;
        }

        if (!$this->config->hasProfile($profileId)) {
            // profile no longer exists, simply delete the configuration
            // (if we are not prevented)
            if (0 === (self::DO_NOT_DELETE & $optionFlags)) {
                if ('openvpn' === $vpnProto) {
                    $this->storage->oCertDelete($userId, $connectionId);
                }
                if ('wireguard' === $vpnProto) {
                    $this->storage->wPeerRemove($userId, $connectionId);
                }
            }

            return;
        }

        $profileConfig = $this->config->profileConfig($profileId);

        switch ($vpnProto) {
            case 'openvpn':
                if (0 === (self::DO_NOT_DELETE & $optionFlags)) {
                    $this->storage->oCertDelete($userId, $connectionId);
                }
                $this->vpnDaemon->oDisconnectClient($profileConfig->nodeUrl($nodeNumber), $connectionId);

                break;

            case 'wireguard':
                if (0 === (self::DO_NOT_DELETE & $optionFlags)) {
                    $this->storage->wPeerRemove($userId, $connectionId);
                }
                if (null !== $peerInfo = $this->vpnDaemon->wPeerRemove($profileConfig->nodeUrl($nodeNumber), $connectionId)) {
                    // XXX what if peer was not connected/registered anywhere?
                    // peer was connected to this node, use the information
                    // we got back to call "clientDisconnect"
                    $this->storage->clientDisconnect($connectionId, $peerInfo['bytes_in'], $peerInfo['bytes_out'], $this->dateTime);

                    foreach ($this->connectionHookList as $connectionHook) {
                        [$ipFour, $ipSix] = self::extractAddresses($peerInfo['ip_net']);

                        try {
                            $connectionHook->disconnect($userId, $profileId, $connectionId, $ipFour, $ipSix);
                        } catch (ConnectionHookException $e) {
                            $this->logger->warning(sprintf('%s: %s', \get_class($connectionHook), $e->getMessage()));
                            // we don't react to this any further, as client
                            // wants to leave, we can't stop them anyway
                        }
                    }
                }
        }
    }

    protected function getRandomBytes(): string
    {
        return random_bytes(32);
    }

    /**
     * @param array<string> $ipNet
     *
     * @return array{0:string,1:string}
     */
    private static function extractAddresses(array $ipNet): array
    {
        // the VPN daemon API returns any number of IP addresses, which is not
        // what we want here. We assume we'll have both an IPv4 and IPv6
        // address in the result set and return them "in order", just like it
        // is done for OpenVPN
        $ipFour = '?';
        $ipSix = '?';
        foreach ($ipNet as $ipPrefix) {
            $clientIp = Ip::fromIpPrefix($ipPrefix);
            if (Ip::IP_4 === $clientIp->family()) {
                $ipFour = $clientIp->address();

                continue;
            }

            $ipSix = $clientIp->address();
        }

        return [$ipFour, $ipSix];
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
        $this->storage->clientConnect($userId, $profileConfig->profileId(), 'wireguard', $publicKey, $ipFour, $ipSix, $this->dateTime);

        foreach ($this->connectionHookList as $connectionHook) {
            try {
                $connectionHook->connect($userId, $profileConfig->profileId(), $publicKey, $ipFour, $ipSix, null);
            } catch (ConnectionHookException $e) {
                throw new ConnectionManagerException(\get_class($connectionHook).': '.$e->getMessage());
            }
        }

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

    /**
     * @return array{0:?string,1:?int}
     */
    private function determineVpnProtoNodeNumber(string $userId, string $profileId, string $connectionId): array
    {
        if (null !== $nodeNumber = $this->storage->wNodeNumber($userId, $profileId, $connectionId)) {
            return ['wireguard', $nodeNumber];
        }
        if (null !== $nodeNumber = $this->storage->oNodeNumber($userId, $profileId, $connectionId)) {
            return ['openvpn', $nodeNumber];
        }

        return [null, null];
    }
}
