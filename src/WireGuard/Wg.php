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
// XXX introduce WgException?
use fkooman\OAuth\Server\AccessToken;
use LC\Portal\ProfileConfig;
use LC\Portal\Storage;
use RuntimeException;

/**
 * Obtain and register a WireGuard configuration file.
 */
class Wg
{
    private WgDaemon $wgDaemon;
    private Storage $storage;
    private DateTimeImmutable $dateTime;

    public function __construct(WgDaemon $wgDaemon, Storage $storage)
    {
        $this->wgDaemon = $wgDaemon;
        $this->storage = $storage;
        $this->dateTime = new DateTimeImmutable();
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

        if (null === $ipInfo = $this->getIpAddress($profileConfig)) {
            // unable to get new IP address to assign to peer
            throw new RuntimeException('unable to get a an IP address');
        }
        [$ipFour, $ipSix] = $ipInfo;

        // store peer in the DB
        // XXX we should override this public key if it already exists here
        $this->storage->wgAddPeer($userId, $profileConfig->profileId(), $displayName, $publicKey, $ipFour, $ipSix, $expiresAt, $accessToken);

        // add peer to WG
        // XXX make sure the public key config is overriden if the public key already exists
        $this->wgDaemon->addPeer('http://'.$profileConfig->nodeIp().':8080', $publicKey, $ipFour, $ipSix);

        // XXX we do not need to get the public key from the daemon!
        $wgInfo = $this->wgDaemon->getInfo('http://'.$profileConfig->nodeIp().':8080');

        // add connection log entry
        // XXX if we have an "open" log for this publicKey, close it first, i guess that is what "clientLost" indicator is for?
        $this->storage->clientConnect($profileConfig->profileId(), $publicKey, $ipFour, $ipSix, $this->dateTime);

        return new WgConfig(
            $profileConfig,
            $publicKey,
            $privateKey,
            $ipFour,
            $ipSix,
            $wgInfo['PublicKey']
        );
    }

    public function removePeer(ProfileConfig $profileConfig, string $userId, string $publicKey): void
    {
        $this->storage->wgRemovePeer($userId, $publicKey);
        // XXX we have to make sure the user owns the public key, otherwise it can be used to disconnect other users!
        // XXX what if multiple users use the same wireguard public key? that won't work and that is good!
        $peerInfo = $this->wgDaemon->removePeer('http://'.$profileConfig->nodeIp().':8080', $publicKey);

        $bytesTransferred = 0;
        if (\array_key_exists('BytesTransferred', $peerInfo) && \is_int($peerInfo['BytesTransferred'])) {
            $bytesTransferred = $peerInfo['BytesTransferred'];
        }

        $ipFour = '0.0.0.0/32';
        $ipSix = '::/32';
        foreach ($peerInfo['AllowedIPs'] as $ip) {
            if (false !== strpos($ip, ':')) {
                [$ipSix, ] = explode('/', $ip);
                continue;
            }
            [$ipFour, ] = explode('/', $ip);
        }

        // close connection log
        // XXX we should simplify connection log in that closing it does not
        // require ip4/ip6 and just make sure one CN/public key can only be used one at a
        // time... this may be easy for WG, but difficult for OpenVPN, so we
        // should disconnect the other connection if it is already enabled when
        // connecting new
        $this->storage->clientDisconnect($profileConfig->profileId(), $publicKey, $ipFour, $ipSix, $this->dateTime, $bytesTransferred);
    }

    /**
     * @return ?array{0:string,1:string}
     */
    private function getIpAddress(ProfileConfig $profileConfig): ?array
    {
        // make a list of all allocated IPv4 addresses (the IPv6 address is
        // based on the IPv4 address)
        $allocatedIpFourList = $this->storage->wgGetAllocatedIpFourAddresses();
        $ipInRangeList = self::getIpInRangeList($profileConfig->range());
        foreach ($ipInRangeList as $ipInRange) {
            if (!\in_array($ipInRange, $allocatedIpFourList, true)) {
                // include this IPv4 address in IPv6 address
                [$ipSixAddress, $ipSixPrefix] = explode('/', $profileConfig->range6());
                $ipSixPrefix = (int) $ipSixPrefix;
                $ipFourHex = bin2hex(inet_pton($ipInRange));
                $ipSixHex = bin2hex(inet_pton($ipSixAddress));
                // clear the last $ipSixPrefix/4 elements
                $ipSixHex = substr_replace($ipSixHex, str_repeat('0', (int) ($ipSixPrefix / 4)), -((int) ($ipSixPrefix / 4)));
                $ipSixHex = substr_replace($ipSixHex, $ipFourHex, -8);
                $ipSix = inet_ntop(hex2bin($ipSixHex));

                return [$ipInRange, $ipSix];
            }
        }

        return null;
    }

    /**
     * @return array<string>
     */
    private static function getIpInRangeList(string $ipAddressPrefix): array
    {
        [$ipAddress, $ipPrefix] = explode('/', $ipAddressPrefix);
        $ipPrefix = (int) $ipPrefix;
        $ipNetmask = long2ip(-1 << (32 - $ipPrefix));
        $ipNetwork = long2ip(ip2long($ipAddress) & ip2long($ipNetmask));
        $numberOfHosts = (int) 2 ** (32 - $ipPrefix) - 2;
        if ($ipPrefix > 30) {
            return [];
        }
        $hostList = [];
        for ($i = 2; $i <= $numberOfHosts; ++$i) {
            $hostList[] = long2ip(ip2long($ipNetwork) + $i);
        }

        return $hostList;
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
        passthru("echo $privateKey | /usr/bin/wg pubkey");

        return trim(ob_get_clean());
    }
}
