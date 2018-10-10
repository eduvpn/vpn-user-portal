<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Server\Exception\InvalidRequestException;
use fkooman\OAuth\Server\Exception\ServerErrorException;
use fkooman\OAuth\Server\SignerInterface;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\ConstantTime\Binary;
use RangeException;
use SURFnet\VPN\Common\Json;

class SodiumSigner implements SignerInterface
{
    /** @var string */
    private $secretKey;

    /** @var array */
    private $publicKeyList = [];

    /**
     * @param string $keyPair
     * @param array  $publicKeyList
     */
    public function __construct($keyPair, array $publicKeyList = [])
    {
        if (SODIUM_CRYPTO_SIGN_KEYPAIRBYTES !== Binary::safeStrlen($keyPair)) {
            throw new ServerErrorException('invalid keypair length');
        }
        $this->secretKey = \sodium_crypto_sign_secretkey($keyPair);
        $this->publicKeyList['local'] = \sodium_crypto_sign_publickey($keyPair);

        foreach ($publicKeyList as $keyId => $publicKey) {
            if (!\is_string($keyId) || 0 >= Binary::safeStrlen($keyId) || 'local' === $keyId) {
                throw new ServerErrorException('keyId MUST be non-empty string != "local"');
            }
            if (SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== Binary::safeStrlen($publicKey)) {
                throw new ServerErrorException(\sprintf('invalid public key length for key "%s"', $keyId));
            }
            $this->publicKeyList[$keyId] = $publicKey;
        }
    }

    /**
     * @param array $listOfClaims
     *
     * @return string
     */
    public function sign(array $listOfClaims)
    {
        return Base64UrlSafe::encodeUnpadded(
            \sodium_crypto_sign(Json::encode($listOfClaims), $this->secretKey)
        );
    }

    /**
     * @param string $inputTokenStr
     *
     * @return false|array
     */
    public function verify($inputTokenStr)
    {
        try {
            $decodedTokenStr = Base64UrlSafe::decode(
                self::toUrlSafeUnpadded($inputTokenStr)
            );

            foreach ($this->publicKeyList as $keyId => $publicKey) {
                $jsonString = \sodium_crypto_sign_open($decodedTokenStr, $publicKey);
                if (false !== $jsonString) {
                    $listOfClaims = Json::decode($jsonString);
                    $listOfClaims['key_id'] = $keyId;

                    return $listOfClaims;
                }
            }

            return false;
        } catch (RangeException $e) {
            // this indicates the provided Base64 encoded token is malformed,
            // this is an "user" error!
            throw new InvalidRequestException('unable to decode Base64');
        }
    }

    /**
     * @param string $str
     *
     * @return string
     */
    private static function toUrlSafeUnpadded($str)
    {
        // in earlier versions we supported standard Base64 encoding as well,
        // now we only generate Base64UrlSafe strings (without padding), but
        // we want to accept the old ones as well!
        return \str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            $str
        );
    }
}
