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
// XXX introduce WgException?
use LC\Portal\Dt;
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

    // XXX move DateTime to getConfig caller
    private DateTimeImmutable $dateTime;

    public function __construct(WgDaemon $wgDaemon, Storage $storage)
    {
        $this->wgDaemon = $wgDaemon;
        $this->storage = $storage;
        $this->dateTime = Dt::get();
    }

    /**
     * XXX want only 1 code path both for portal and for API.
     * XXX why can accesstoken be null? from portal?
     */
    public function getConfig(ProfileConfig $profileConfig, string $userId, string $displayName, ?AccessToken $accessToken, ?string $publicKey): WgConfig
    {
        $privateKey = null;
        if (null === $publicKey) {
            $privateKey = self::generatePrivateKey();
            $publicKey = self::generatePublicKey($privateKey);
        }

        if (null === $ipInfo = $this->getIpAddress($profileConfig)) {
            // unable to get new IP address to assign to peer
            throw new RuntimeException('unable to get a an IP address');
        }
        [$ipFour, $ipSix] = $ipInfo;

        // store peer in the DB
        // XXX needs expiresat!
        // XXX  should we do this (db) outside the Wg class?
        $this->storage->wgAddPeer($userId, $profileConfig->profileId(), $displayName, $publicKey, $ipFour, $ipSix, $this->dateTime, $accessToken);

        // add peer to WG
        $this->wgDaemon->addPeer('http://'.$profileConfig->nodeIp().':8080', $publicKey, $ipFour, $ipSix);

        $wgInfo = $this->wgDaemon->getInfo('http://'.$profileConfig->nodeIp().':8080');

        return new WgConfig(
            $profileConfig,
            $publicKey,
            $privateKey,
            $ipFour,
            $ipSix,
            $wgInfo['PublicKey']
        );
    }

    public function disconnectPeer(ProfileConfig $profileConfig, string $userId, string $publicKey): void
    {
        $this->storage->wgRemovePeer($userId, $publicKey);
        // XXX we have to make sure the user owns the public key, otherwise it can be used to disconnect other users!
        // XXX what if multiple users use the same wireguard public key?
        $this->wgDaemon->removePeer('http://'.$profileConfig->nodeIp().':8080', $publicKey);
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

    private static function generatePublicKey(string $privateKey): string
    {
        ob_start();
        passthru("echo $privateKey | /usr/bin/wg pubkey");

        return trim(ob_get_clean());
    }
}
