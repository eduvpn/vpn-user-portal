<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\WireGuard;

use Vpn\Portal\Base64;

/**
 * Implentation of wg genkey / wg pubkey using libsodium.
 */
class Key
{
    public static function generate(): string
    {
        return Base64::encode(sodium_crypto_box_secretkey(sodium_crypto_box_keypair()));
    }

    public static function publicKeyFromSecretKey(string $secretKey): string
    {
        return Base64::encode(sodium_crypto_box_publickey_from_secretkey(Base64::decode($secretKey)));
    }
}
