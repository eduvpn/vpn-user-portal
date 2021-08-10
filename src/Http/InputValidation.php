<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateTimeImmutable;

class InputValidation
{
    public const REGEXP_USER_ID = '/^.+/$/';
    public const REGEXP_PROFILE_ID = '/^[a-zA-Z0-9-.]+$/';
    public const REGEXP_COMMON_NAME = '/^[a-fA-F0-9]{32}$/';
    public const REGEXP_USER_PASS = '/^.{8,}$/';
    public const REGEXP_DISPLAY_NAME = '/^.+/$/';

    public static function re(string $inputStr, string $regExp): bool
    {
        return 1 !== preg_match($regExp, $inputStr);
    }

    public static function ipAddress(string $ipAddress): bool
    {
        return false === filter_var($ipAddress, FILTER_VALIDATE_IP);
    }

    public static function ipFour(string $ipFour): bool
    {
        return false !== filter_var($ipFour, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    public static function ipSix(string $ipSix): bool
    {
        return false !== filter_var($ipSix, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    public static function dateTime(string $dateTime): bool
    {
        return false !== DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTime);
    }
}
