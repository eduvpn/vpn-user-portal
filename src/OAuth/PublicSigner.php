<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\OAuth;

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

    /**
     * @param \fkooman\Jwt\Keys\EdDSA\PublicKey      $publicKey
     * @param \fkooman\Jwt\Keys\EdDSA\SecretKey|null $secretKey
     */
    public function __construct(PublicKey $publicKey, SecretKey $secretKey = null)
    {
        $this->edDsa = new EdDSA($publicKey, $secretKey);
        $this->edDsa->useKeyId(true);
    }

    /**
     * @param string $providedToken
     *
     * @return string|null
     */
    public static function extractKid($providedToken)
    {
        return EdDSA::extractKeyId($providedToken);
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
