<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

/**
 * Compatibility layer for libsodium with namespace. We *require* PHP >= 7.2
 * sodium OR pecl-libsodium.
 *
 * Method PHPdoc inspired by paragonie/sodium_compat
 *
 * @see https://github.com/paragonie/sodium_compat
 */
if (!is_callable('sodium_crypto_sign_publickey')) {
    /**
     * @param string $keypair
     *
     * @return string
     */
    function sodium_crypto_sign_publickey($keypair)
    {
        return \Sodium\crypto_sign_publickey($keypair);
    }
}

if (!is_callable('sodium_crypto_sign_verify_detached')) {
    /**
     * @param string $signature
     * @param string $message
     * @param string $publicKey
     *
     * @return bool
     */
    function sodium_crypto_sign_verify_detached($signature, $message, $publicKey)
    {
        return \Sodium\crypto_sign_verify_detached($signature, $message, $publicKey);
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
