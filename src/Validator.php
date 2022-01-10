<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use DateTimeImmutable;
use RangeException;

/**
 * This class MUST contain all (input) validation methods that are used
 * throughout the code. Each method MUST throw a "RangeException" in case the
 * validation fails.
 */
class Validator
{
    private const REGEXP_USER_ID = '/^.+$/';
    private const REGEXP_USER_AUTH_PASS = '/^.+$/';
    private const REGEXP_USER_PASS = '/^.{8,}$/';
    private const REGEXP_DISPLAY_NAME = '/^.+$/';
    /** @see https://lore.kernel.org/wireguard/X+UkseUOEY1sVDEe@zx2c4.com/ */
    private const REGEXP_CONNECTION_ID = '/^[A-Za-z0-9+\\/]{42}[A|E|I|M|Q|U|Y|c|g|k|o|s|w|4|8|0]=$/';
    private const REGEXP_AUTH_KEY = '/^[A-Za-z0-9-_]+$/';
    private const REGEXP_PROFILE_ID = '/^[a-zA-Z0-9-.]+$/';
    private const REGEXP_SERVER_NAME = '/^[a-zA-Z0-9-.]+$/';

    /**
     * @throws \RangeException
     */
    public static function displayName(string $displayName): void
    {
        self::re($displayName, self::REGEXP_DISPLAY_NAME, __FUNCTION__);
    }

    /**
     * @throws \RangeException
     */
    public static function authKey(string $authKey): void
    {
        self::re($authKey, self::REGEXP_AUTH_KEY, __FUNCTION__);
    }

    /**
     * @throws \RangeException
     */
    public static function userId(string $userId): void
    {
        self::re($userId, self::REGEXP_USER_ID, __FUNCTION__);
    }

    /**
     * @throws \RangeException
     */
    public static function userPass(string $userPass): void
    {
        self::re($userPass, self::REGEXP_USER_PASS, __FUNCTION__);
    }

    /**
     * @throws \RangeException
     */
    public static function connectionId(string $connectionId): void
    {
        self::re($connectionId, self::REGEXP_CONNECTION_ID, __FUNCTION__);
    }

    /**
     * Validate WireGuard Public Key, a Base64 coded 32 byte string.
     *
     * @throws \RangeException
     */
    public static function publicKey(string $publicKey): void
    {
        self::re($publicKey, self::REGEXP_CONNECTION_ID, __FUNCTION__);
    }

    /**
     * Validate OpenVPN X.509 certificate "Common Name", a Base64 encoded 32
     * bytes random string.
     *
     * @throws \RangeException
     */
    public static function commonName(string $commonName): void
    {
        self::re($commonName, self::REGEXP_CONNECTION_ID, __FUNCTION__);
    }

    /**
     * @throws \RangeException
     */
    public static function userAuthPass(string $userAuthPass): void
    {
        self::re($userAuthPass, self::REGEXP_USER_AUTH_PASS, __FUNCTION__);
    }

    /**
     * @throws \RangeException
     */
    public static function serverName(string $serverName): void
    {
        self::re($serverName, self::REGEXP_SERVER_NAME, __FUNCTION__);
    }

    /**
     * @throws \RangeException
     */
    public static function profileId(string $profileId): void
    {
        self::re($profileId, self::REGEXP_PROFILE_ID, __FUNCTION__);
    }

    /**
     * @param array<string> $profileIdList
     *
     * @throws \RangeException
     */
    public static function profileIdList(array $profileIdList): void
    {
        foreach ($profileIdList as $profileId) {
            self::re($profileId, self::REGEXP_PROFILE_ID, __FUNCTION__);
        }
    }

    /**
     * @throws \RangeException
     */
    public static function ipAddress(string $ipAddress): void
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new RangeException('not an IP address');
        }
    }

    /**
     * @throws \RangeException
     */
    public static function ipFour(string $ipFour): void
    {
        if (false === filter_var($ipFour, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RangeException('not an IPv4 address');
        }
    }

    /**
     * @throws \RangeException
     */
    public static function ipSix(string $ipSix): void
    {
        if (false === filter_var($ipSix, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new RangeException('not an IPv6 address');
        }
    }

    /**
     * @throws \RangeException
     */
    public static function dateTime(string $dateTime): void
    {
        if (false === DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTime)) {
            throw new RangeException('not a valid date/time format');
        }
    }

    public static function yesOrNo(string $yesOrNo): void
    {
        self::inSet($yesOrNo, ['yes', 'no']);
    }

    public static function vpnProto(string $vpnProto): void
    {
        self::inSet($vpnProto, ['openvpn', 'wireguard', 'default']);
    }

    public static function onOrOff(string $onOrOff): void
    {
        self::inSet($onOrOff, ['on', 'off']);
    }

    public static function listUsers(string $listUsers): void
    {
        self::inSet($listUsers, ['all', 'disabled_only']);
    }

    public static function nodeNumber(string $nodeNumber): void
    {
        self::nonNegativeInt($nodeNumber);
    }

    public static function nonNegativeInt(string $nonNegativeInt): void
    {
        // cast string to int and compare the unsigned int representation as
        // string with the provided input to make sure we have a number >= 0
        if ($nonNegativeInt !== sprintf('%u', (int) $nonNegativeInt)) {
            throw new RangeException('integer not >= 0');
        }
    }

    public static function languageCode(string $languageCode): void
    {
        if (!\array_key_exists($languageCode, Tpl::supportedLanguages())) {
            throw new RangeException('invalid language code');
        }
    }

    public static function matchesOrigin(string $httpOrigin, string $urlToMatch): void
    {
        $urlScheme = parse_url($urlToMatch, PHP_URL_SCHEME);
        if ('https' !== $urlScheme && 'http' !== $urlScheme) {
            // only https/http is supported
            throw new RangeException('URL must match "Origin"');
        }
        if (null !== parse_url($urlToMatch, PHP_URL_USER)) {
            // URL MUST NOT contain authentication information (DEC-01-001 WP1)
            // before PHP 7.4.14 & 7.3.26 there was a bug that invalid userinfo
            // appeared as PHP_URL_USER instead of as part of the hostname,
            // @see https://bugs.php.net/bug.php?id=77423
            throw new RangeException('URL must match "Origin"');
        }
        $urlHost = parse_url($urlToMatch, PHP_URL_HOST);
        if (!\is_string($urlHost)) {
            // not a valid host
            throw new RangeException('URL must match "Origin"');
        }
        $constructedUrl = $urlScheme.'://'.$urlHost;
        if (null !== $urlPort = parse_url($urlToMatch, PHP_URL_PORT)) {
            $constructedUrl .= ':'.(string) $urlPort;
        }

        if ($httpOrigin !== $constructedUrl) {
            throw new RangeException('URL must match "Origin"');
        }
    }

    /**
     * @param array<string> $setHaystack
     */
    public static function inSet(string $setNeedle, array $setHaystack): void
    {
        // XXX use inSet also for other "in set" functions in this file
        if (!\in_array($setNeedle, $setHaystack, true)) {
            throw new RangeException('provided value "'.$setNeedle.'" not any of {'.implode(',', $setHaystack).'}');
        }
    }

    /**
     * @throws \RangeException
     */
    private static function re(string $inputStr, string $regExp, string $errorKey): void
    {
        if (1 !== preg_match($regExp, $inputStr)) {
            // XXX we MUST NOT show inputStr here in case it is password!
            throw new RangeException('invalid value for "'.$errorKey.'"');
        }
    }
}
