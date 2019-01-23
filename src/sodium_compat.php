<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

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
