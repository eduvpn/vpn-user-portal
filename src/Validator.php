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
use RangeException;

class Validator
{
    public const REGEXP_USER_ID = '/^.+$/';
    public const REGEXP_COMMON_NAME = '/^[a-fA-F0-9]{32}$/';
    public const REGEXP_USER_AUTH_PASS = '/^.+$/';
    public const REGEXP_USER_PASS = '/^.{8,}$/';
    public const REGEXP_DISPLAY_NAME = '/^.+$/';
    public const REGEXP_PUBLIC_KEY = '/^[A-Za-z0-9+\\/\\=]+$/';    // XXX improve this!
    public const REGEXP_AUTH_KEY = '/^[A-Za-z0-9-_]+$/';
    private const REGEXP_PROFILE_ID = '/^[a-zA-Z0-9-.]+$/';

    /**
     * @throws \RangeException
     */
    public static function re(string $inputStr, string $regExp): void
    {
        if (1 !== preg_match($regExp, $inputStr)) {
            throw new RangeException();
        }
    }

    /**
     * @throws \RangeException
     */
    public static function profileId(string $profileId): void
    {
        self::re($profileId, self::REGEXP_PROFILE_ID);
    }

    /**
     * @throws \RangeException
     */
    public static function ipAddress(string $ipAddress): void
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new RangeException();
        }
    }

    /**
     * @throws \RangeException
     */
    public static function ipFour(string $ipFour): void
    {
        if (false === filter_var($ipFour, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RangeException();
        }
    }

    /**
     * @throws \RangeException
     */
    public static function ipSix(string $ipSix): void
    {
        if (false === filter_var($ipSix, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new RangeException();
        }
    }

    /**
     * @throws \RangeException
     */
    public static function dateTime(string $dateTime): void
    {
        if (false === DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTime)) {
            throw new RangeException();
        }
    }
}
