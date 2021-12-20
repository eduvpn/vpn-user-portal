<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use DateTimeImmutable;
use Vpn\Portal\Exception\ConnectionManagerException;
use Vpn\Portal\OpenVpn\ClientConfig as OpenVpnClientConfig;
use Vpn\Portal\WireGuard\ClientConfig as WireGuardClientConfig;
use Vpn\Portal\WireGuard\KeyPair;

/**
 * List, add and remove connections.
 */
class ConnectionManager
{
    public const DO_NOT_DELETE = 1;

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

            if ($profileConfig->oSupport()) {
                // OpenVPN
                $oCertListByProfileId = $this->storage->oCertListByProfileId($profileId, Storage::INCLUDE_EXPIRED);

                $oConnectionList = [];
                for ($i = 0; $i < $profileConfig->nodeCount(); ++$i) {
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
            }

            if ($profileConfig->wSupport()) {
                // WireGuard
                $wPeerListByProfileId = $this->storage->wPeerListByProfileId($profileId, Storage::INCLUDE_EXPIRED);
                $wPeerList = [];
                for ($i = 0; $i < $profileConfig->nodeCount(); ++$i) {
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

    public function connect(ServerInfo $serverInfo, string $userId, string $profileId, string $useProto, string $displayName, DateTimeImmutable $expiresAt, bool $tcpOnly, ?string $publicKey, ?string $authKey): ClientConfigInterface
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

        // XXX we have to allow the client to *prefer* a protocol, now it will
        // always return openvpn if openvpn is supported...
        if ('openvpn' === $useProto && $profileConfig->oSupport()) {
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
                $tcpOnly
            );
        }

        if ('wireguard' === $useProto && $profileConfig->wSupport()) {
            // WireGuard
            $privateKey = null;
            if (null === $publicKey) {
                $keyPair = KeyPair::generate();
                $privateKey = $keyPair['secret_key'];
                $publicKey = $keyPair['public_key'];
            }

            // XXX this call can throw a ConnectionManagerException!
            [$ipFour, $ipSix] = $this->getIpAddress($profileConfig, $nodeNumber);

            // XXX we MUST make sure public_key is unique on this server!!!
            // the DB enforces this, but maybe a better error could be given?
            $this->storage->wPeerAdd($userId, $profileId, $displayName, $publicKey, $ipFour, $ipSix, $expiresAt, $authKey);
            $this->vpnDaemon->wPeerAdd($profileConfig->nodeUrl($nodeNumber), $publicKey, $ipFour, $ipSix);
            $this->storage->clientConnect($userId, $profileId, $publicKey, $ipFour, $ipSix, new DateTimeImmutable());

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

        throw new ConnectionManagerException(sprintf('unsupported protocol "%s" for profile "%s"', $useProto, $profileId));
    }

    public function disconnect(string $userId, string $profileId, string $connectionId, int $optionFlags = 0): void
    {
        if (!$this->config->hasProfile($profileId)) {
            // profile does not exist (anymore)
            // try to delete them anyway if we are not prevented...
            if (0 === (self::DO_NOT_DELETE & $optionFlags)) {
                $this->storage->oCertDelete($userId, $connectionId);
                $this->storage->wPeerRemove($userId, $connectionId);
            }

            return;
        }
        $profileConfig = $this->config->profileConfig($profileId);

        for ($i = 0; $i < $profileConfig->nodeCount(); ++$i) {
            // XXX figure out whether the connection is OpenVPN or WireGuard
            // instead of trying both... it doesn't hurt, but could be more
            // efficient...
            // XXX storage->clientDisconnect should only be called for wireguard, and only once, not for every node!
            if ($profileConfig->oSupport()) {
                if (0 === (self::DO_NOT_DELETE & $optionFlags)) {
                    $this->storage->oCertDelete($userId, $connectionId);
                }
                $this->vpnDaemon->oDisconnectClient($profileConfig->nodeUrl($i), $connectionId);
            }

            if ($profileConfig->wSupport()) {
                if (0 === (self::DO_NOT_DELETE & $optionFlags)) {
                    $this->storage->wPeerRemove($userId, $connectionId);
                }
                $this->vpnDaemon->wPeerRemove($profileConfig->nodeUrl($i), $connectionId);
                $this->storage->clientDisconnect($userId, $profileId, $connectionId, new DateTimeImmutable());
            }
        }
    }

    /**
     * @return array{0:string,1:string}
     */
    private function getIpAddress(ProfileConfig $profileConfig, int $nodeNumber): array
    {
        // make a list of all allocated IPv4 addresses (the IPv6 address is
        // based on the IPv4 address)
        $allocatedIpFourList = $this->storage->wgGetAllocatedIpFourAddresses();
        $ipFourInRangeList = $profileConfig->wRangeFour($nodeNumber)->clientIpListFour();
        $ipSixInRangeList = $profileConfig->wRangeSix($nodeNumber)->clientIpListSix(\count($ipFourInRangeList));
        foreach ($ipFourInRangeList as $k => $ipFourInRange) {
            if (!\in_array($ipFourInRange, $allocatedIpFourList, true)) {
                return [$ipFourInRange, $ipSixInRangeList[$k]];
            }
        }

        throw new ConnectionManagerException('no free IP address');
    }
}
