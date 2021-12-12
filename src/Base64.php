<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

/**
 * Wrapper class around sodium base64 encoding/decoding functions using the
 * paragonie/constant_time_encoding API.
 */
class Base64
{
    public static function encode(string $string): string
    {
        return sodium_bin2base64($string, SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    public static function decode(string $string): string
    {
        return sodium_base642bin($string, SODIUM_BASE64_VARIANT_ORIGINAL);
    }
}
