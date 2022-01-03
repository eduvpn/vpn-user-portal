<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

/**
 * Taken from paragonie/constant_time_encoding (under MIT license).
 */
class Binary
{
    /**
     * Safe substring.
     *
     * @ref mbstring.func_overload
     *
     * @staticvar boolean $exists
     *
     * @param int $length
     *
     * @throws \TypeError
     */
    public static function safeSubstr(
        string $str,
        int $start = 0,
        $length = null
    ): string {
        if (0 === $length) {
            return '';
        }
        if (\function_exists('mb_substr')) {
            return mb_substr($str, $start, $length, '8bit');
        }
        // Unlike mb_substr(), substr() doesn't accept NULL for length
        if (null !== $length) {
            return substr($str, $start, $length);
        }

        return substr($str, $start);
    }

    /**
     * Safe string length.
     *
     * @ref mbstring.func_overload
     */
    public static function safeStrlen(string $str): int
    {
        if (\function_exists('mb_strlen')) {
            return mb_strlen($str, '8bit');
        }

        return \strlen($str);
    }
}
