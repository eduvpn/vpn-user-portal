<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\WireGuard;

use LC\Portal\Base64;

class KeyPair
{
    /**
     * Get Base64 encoded secret and public key.
     *
     * @return array{secret_key:string,public_key:string}
     */
    public static function generate(): array
    {
        $keyPair = sodium_crypto_box_keypair();

        return [
            'secret_key' => Base64::encode(sodium_crypto_box_secretkey($keyPair)),
            'public_key' => Base64::encode(sodium_crypto_box_publickey($keyPair)),
        ];
    }
}
