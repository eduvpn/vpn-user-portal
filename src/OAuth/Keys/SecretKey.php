<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\OAuth\Keys;

use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\ConstantTime\Binary;

class SecretKey
{
    /** @var string */
    private $secretKey;

    /**
     * @param string $secretKey
     */
    public function __construct($secretKey)
    {
        switch (Binary::safeStrlen($secretKey)) {
            case SODIUM_CRYPTO_SIGN_SECRETKEYBYTES:
                $this->secretKey = $secretKey;
                break;
            case SODIUM_CRYPTO_SIGN_SEEDBYTES:
                $this->secretKey = Binary::safeSubstr(sodium_crypto_sign_seed_keypair($secretKey), 0, 64);
                break;
            case SODIUM_CRYPTO_SIGN_KEYPAIRBYTES:
                $this->secretKey = Binary::safeSubstr($secretKey, 0, 64);
                break;
            default:
                throw new \LengthException('invalid secret key length');
        }
    }

    /**
     * @return self
     */
    public static function generate()
    {
        return new self(
            sodium_crypto_sign_secretkey(
                sodium_crypto_sign_keypair()
            )
        );
    }

    /**
     * @return string
     */
    public function encode()
    {
        return Base64UrlSafe::encodeUnpadded($this->secretKey);
    }

    /**
     * @param string $encodedString
     *
     * @return self
     */
    public static function fromEncodedString($encodedString)
    {
        return new self(Base64UrlSafe::decode($encodedString));
    }

    /**
     * @return PublicKey
     */
    public function getPublicKey()
    {
        return new PublicKey(
            sodium_crypto_sign_publickey_from_secretkey($this->secretKey)
        );
    }

    /**
     * @return string
     */
    public function raw()
    {
        return $this->secretKey;
    }
}
