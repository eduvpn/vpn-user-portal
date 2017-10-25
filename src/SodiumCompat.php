<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

/**
 * Class to work as a compatibility layer to support all versions of PHP
 * sodium integration out there.
 *
 * This class supports:
 * - PECL libsodium 1.x for older version of PHP (EPEL)
 * - PECL libsodium 2.x for PHP >= 7.0
 * - PHP sodium for PHP >= 7.2
 *
 * Method PHPdoc shamelessly taken from paragonie/sodium_compat
 *
 * @see https://github.com/paragonie/sodium_compat
 */
class SodiumCompat
{
    /**
     * @param string $keypair
     *
     * @return string
     */
    public static function crypto_sign_publickey($keypair)
    {
        if (is_callable('sodium_crypto_sign_publickey')) {
            return sodium_crypto_sign_publickey($keypair);
        }

        return \Sodium\crypto_sign_publickey($keypair);
    }

    /**
     * @param string $signature
     * @param string $message
     * @param string $publicKey
     *
     * @return bool
     */
    public static function crypto_sign_verify_detached($signature, $message, $publicKey)
    {
        if (is_callable('sodium_crypto_sign_verify_detached')) {
            return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
        }

        return \Sodium\crypto_sign_verify_detached($signature, $message, $publicKey);
    }

    /**
     * @return string
     */
    public static function crypto_sign_keypair()
    {
        if (is_callable('sodium_crypto_sign_keypair')) {
            return sodium_crypto_sign_keypair();
        }

        return \Sodium\crypto_sign_keypair();
    }
}
