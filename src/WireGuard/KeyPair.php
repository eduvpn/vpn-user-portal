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

/**
 * Generate a keypair using libsodium functions. The output of "public_key" is
 * exactly the same when using the "wg pubkey" command to convert the
 * "secret_key" to "public_key" so we are quite confident this is a good
 * approach to avoid needing "exec".
 */
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
