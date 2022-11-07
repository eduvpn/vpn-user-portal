<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Crypto;

use Vpn\Portal\Base64UrlSafe;

class Hmac
{
    public static function generate(string $m, HmacKey $hmacKey): string
    {
        return Base64UrlSafe::encodeUnpadded(
            hash_hmac('sha256', $m, $hmacKey->raw(), true)
        );
    }
}
