<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OAuth;

use fkooman\Jwt\EdDSA;
use fkooman\Jwt\Exception\JwtException;
use fkooman\Jwt\Keys\EdDSA\PublicKey;
use fkooman\Jwt\Keys\EdDSA\SecretKey;
use fkooman\OAuth\Server\SignerInterface;

/**
 * JWT Signer, using EdDSA (Ed25519) algorithm.
 */
class PublicSigner implements SignerInterface
{
    /** @var \fkooman\Jwt\EdDSA */
    private $edDsa;

    public function __construct(PublicKey $publicKey, SecretKey $secretKey = null)
    {
        $this->edDsa = new EdDSA($publicKey, $secretKey);
        $this->edDsa->setKeyId(self::calculateKeyId($publicKey));
    }

    /**
     * @return string
     */
    public static function calculateKeyId(PublicKey $publicKey)
    {
        return sodium_bin2base64(
            hash(
                'sha256',
                $publicKey->raw(),
                true
            ),
            \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
        );
    }

    /**
     * @param string $providedToken
     *
     * @return string|null
     */
    public static function extractKid($providedToken)
    {
        try {
            return EdDSA::extractKeyId($providedToken);
        } catch (JwtException $e) {
            // if the token is not a JWT token an exception is thrown, which
            // of course implies we won't have a "kid"
            return null;
        }
    }

    /**
     * @param array<string,mixed> $codeTokenInfo
     *
     * @return string
     */
    public function sign(array $codeTokenInfo)
    {
        return $this->edDsa->encode($codeTokenInfo);
    }

    /**
     * @param string $codeTokenString
     *
     * @return false|array<string,mixed>
     */
    public function verify($codeTokenString)
    {
        try {
            return $this->edDsa->decode($codeTokenString);
        } catch (JwtException $e) {
            return false;
        }
    }
}
