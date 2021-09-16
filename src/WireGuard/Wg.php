<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\WireGuard;

use DateTimeImmutable;
use fkooman\OAuth\Server\AccessToken;
// XXX introduce WgException
use LC\Portal\Dt;
use LC\Portal\IP;
use LC\Portal\ProfileConfig;
use LC\Portal\Storage;
use LC\Portal\WireGuard\Exception\WgException;
use RuntimeException;

/**
 * Obtain and register a WireGuard configuration file.
 */
class Wg
{
    private WgDaemon $wgDaemon;
    private Storage $storage;
    private string $wgPublicKey;
    private DateTimeImmutable $dateTime;

    public function __construct(WgDaemon $wgDaemon, Storage $storage, string $wgPublicKey)
    {
        $this->wgDaemon = $wgDaemon;
        $this->storage = $storage;
        $this->wgPublicKey = $wgPublicKey;
        $this->dateTime = Dt::get();
    }

    /**
     * XXX want only 1 code path both for portal and for API.
     * XXX why can accesstoken be null? from portal?
     */
    public function addPeer(ProfileConfig $profileConfig, string $userId, string $displayName, DateTimeImmutable $expiresAt, ?AccessToken $accessToken, ?string $publicKey): WgConfig
    {
        $privateKey = null;
        if (null === $publicKey) {
            $privateKey = self::generatePrivateKey();
            $publicKey = self::extractPublicKey($privateKey);
        }

        // check whether we already have the peer with this public key,
        // disconnect it if we do...
        if ($this->storage->wgHasPeer($publicKey)) {
            $this->removePeer($profileConfig, $userId, $publicKey);
        }

        if (null === $ipInfo = $this->getIpAddress($profileConfig)) {
            // unable to get new IP address to assign to peer
            throw new RuntimeException('unable to get a an IP address');
        }
        [$ipFour, $ipSix] = $ipInfo;

        // store peer in the DB
        $this->storage->wgAddPeer($userId, $profileConfig->profileId(), $displayName, $publicKey, $ipFour, $ipSix, $expiresAt, $accessToken);

        // add peer to WG
        // XXX make sure the public key config is overriden if the public key already exists
        $this->wgDaemon->addPeer($profileConfig->nodeBaseUrl(), $publicKey, $ipFour, $ipSix);

        // add connection log entry
        // XXX if we have an "open" log for this publicKey, close it first, i guess that is what "clientLost" indicator is for?
        $this->storage->clientConnect($userId, $profileConfig->profileId(), $ipFour, $ipSix, $this->dateTime);

        return new WgConfig(
            $profileConfig,
            $publicKey,
            $privateKey,
            $ipFour,
            $ipSix,
            $this->wgPublicKey,
        );
    }

    public function removePeer(ProfileConfig $profileConfig, string $userId, string $publicKey): void
    {
        $this->storage->wgRemovePeer($userId, $publicKey);
        // XXX we have to make sure the user owns the public key, otherwise it can be used to disconnect other users!
        // XXX what if multiple users use the same wireguard public key? that won't work and that is good!
        $peerInfo = $this->wgDaemon->removePeer($profileConfig->nodeBaseUrl(), $publicKey);

//        $bytesTransferred = 0;
//        if (\array_key_exists('BytesTransferred', $peerInfo) && \is_int($peerInfo['BytesTransferred'])) {
//            $bytesTransferred = $peerInfo['BytesTransferred'];
//        }
        // XXX add bytesTransferred to some global table

        $ipFour = self::extractIpFour($peerInfo['ip_net']);
        $ipSix = self::extractIpSix($peerInfo['ip_net']);

        // close connection log
        // XXX we should simplify connection log in that closing it does not
        // require ip4/ip6 and just make sure one CN/public key can only be used one at a
        // time... this may be easy for WG, but difficult for OpenVPN, so we
        // should disconnect the other connection if it is already enabled when
        // connecting new
        $this->storage->clientDisconnect($userId, $profileConfig->profileId(), $ipFour, $ipSix, $this->dateTime);
    }

    private static function extractIpFour(array $ipNetList): string
    {
        foreach ($ipNetList as $ipNet) {
            if (false === strpos($ipNet, ':')) {
                [$ipFour, ] = explode('/', $ipNet);

                return $ipFour;
            }
        }

        // XXX better error
        throw new WgException('unable to find IPv4 address');
    }

    private static function extractIpSix(array $ipNetList): string
    {
        foreach ($ipNetList as $ipNet) {
            if (false !== strpos($ipNet, ':')) {
                [$ipSix, ] = explode('/', $ipNet);

                return $ipSix;
            }
        }

        // XXX better error
        throw new WgException('unable to find IPv6 address');
    }

    /**
     * @return ?array{0:string,1:string}
     */
    private function getIpAddress(ProfileConfig $profileConfig): ?array
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

        // no free IP available
        return null;
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
