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
 * Wrapper class around sodium hex encoding/decoding functions using the
 * paragonie/constant_time_encoding API.
 */
class Hex
{
    public static function encode(string $string): string
    {
        return sodium_bin2hex($string);
    }
}
