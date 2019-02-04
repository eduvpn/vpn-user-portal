<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

if (!defined('SODIUM_CRYPTO_SIGN_KEYPAIRBYTES')) {
    define('SODIUM_CRYPTO_SIGN_KEYPAIRBYTES', \Sodium\CRYPTO_SIGN_KEYPAIRBYTES);
}

if (!defined('SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES')) {
    define('SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES', \Sodium\CRYPTO_SIGN_PUBLICKEYBYTES);
}

if (!defined('SODIUM_CRYPTO_SIGN_SECRETKEYBYTES')) {
    define('SODIUM_CRYPTO_SIGN_SECRETKEYBYTES', \Sodium\CRYPTO_SIGN_SECRETKEYBYTES);
}

if (!defined('SODIUM_CRYPTO_SIGN_SEEDBYTES')) {
    define('SODIUM_CRYPTO_SIGN_SEEDBYTES', \Sodium\CRYPTO_SIGN_SEEDBYTES);
}

if (!is_callable('sodium_crypto_sign_detached')) {
    /**
     * @param string $message
     * @param string $sk
     *
     * @return string
     */
    function sodium_crypto_sign_detached($message, $sk)
    {
        return \Sodium\crypto_sign_detached($message, $sk);
    }
}

if (!is_callable('sodium_crypto_sign_keypair')) {
    /**
     * @return string
     */
    function sodium_crypto_sign_keypair()
    {
        return \Sodium\crypto_sign_keypair();
    }
}

if (!is_callable('sodium_crypto_sign_publickey_from_secretkey')) {
    /**
     * @param string $sk
     * @param mixed  $keypair
     *
     * @return string
     */
    function sodium_crypto_sign_publickey_from_secretkey($sk)
    {
        return \Sodium\crypto_sign_publickey_from_secretkey($sk);
    }
}

if (!is_callable('sodium_crypto_sign_secretkey')) {
    /**
     * @param string $keypair
     *
     * @return string
     */
    function sodium_crypto_sign_secretkey($keypair)
    {
        return \Sodium\crypto_sign_secretkey($keypair);
    }
}

if (!is_callable('sodium_crypto_sign_seed_keypair')) {
    /**
     * @param string $seed
     *
     * @return string
     */
    function sodium_crypto_sign_seed_keypair($seed)
    {
        return \Sodium\crypto_sign_seed_keypair($seed);
    }
}

if (!is_callable('sodium_crypto_sign_verify_detached')) {
    /**
     * @param string $signature
     * @param string $message
     * @param string $pk
     *
     * @return bool
     */
    function sodium_crypto_sign_verify_detached($signature, $message, $pk)
    {
        return \Sodium\crypto_sign_verify_detached($signature, $message, $pk);
    }
}
