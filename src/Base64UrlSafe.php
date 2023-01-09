<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

/**
 * Wrapper class around sodium base64 encoding/decoding functions using the
 * paragonie/constant_time_encoding API.
 */
class Base64UrlSafe
{
    public static function encodeUnpadded(string $string): string
    {
        return sodium_bin2base64($string, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    public static function decode(string $string): string
    {
        return sodium_base642bin($string, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
