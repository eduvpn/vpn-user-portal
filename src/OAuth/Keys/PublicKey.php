<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\OAuth\Keys;

use LengthException;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\ConstantTime\Binary;

class PublicKey
{
    /** @var string */
    private $publicKey;

    /**
     * @param string $publicKey
     */
    public function __construct($publicKey)
    {
        if (SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== Binary::safeStrlen($publicKey)) {
            throw new LengthException('invalid public key length');
        }
        $this->publicKey = $publicKey;
    }

    /**
     * @return string
     */
    public function encode()
    {
        return Base64UrlSafe::encodeUnpadded($this->publicKey);
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
     * @return string
     */
    public function getKeyId()
    {
        return Base64UrlSafe::encodeUnpadded(
            hash(
                'sha256',
                $this->raw(),
                true
            )
        );
    }

    /**
     * @return string
     */
    public function raw()
    {
        return $this->publicKey;
    }
}
