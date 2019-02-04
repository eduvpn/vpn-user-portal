<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\OAuth;

use fkooman\OAuth\Server\Json;
use fkooman\OAuth\Server\SignerInterface;
use LetsConnect\Portal\OAuth\Keys\PublicKey;
use LetsConnect\Portal\OAuth\Keys\SecretKey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;

/**
 * JWT Signer, using EdDSA (Ed25519) algorithm.
 */
class PublicSigner implements SignerInterface
{
    /** @var Keys\PublicKey */
    private $publicKey;

    /** @var Keys\SecretKey|null */
    private $secretKey;

    /**
     * @param Keys\PublicKey      $publicKey
     * @param Keys\SecretKey|null $secretKey
     */
    public function __construct(PublicKey $publicKey, SecretKey $secretKey = null)
    {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
    }

    /**
     * @param array<string,mixed> $codeTokenInfo
     *
     * @return string
     */
    public function sign(array $codeTokenInfo)
    {
        if (null === $this->secretKey) {
            throw new RuntimeException('secret key not set');
        }

        $headerData = [
            'alg' => 'EdDSA',
            'typ' => 'JWT',
            'kid' => $this->publicKey->getKeyId(),
        ];

        $jwtHeader = Base64UrlSafe::encodeUnpadded(Json::encode($headerData));
        $jwtPayload = Base64UrlSafe::encodeUnpadded(Json::encode($codeTokenInfo));
        $jwtSignature = Base64UrlSafe::encodeUnpadded(
            sodium_crypto_sign_detached(
                $jwtHeader.'.'.$jwtPayload,
                $this->secretKey->raw()
            )
        );

        return $jwtHeader.'.'.$jwtPayload.'.'.$jwtSignature;
    }

    /**
     * @param string $codeTokenString
     *
     * @return false|array<string,mixed>
     */
    public function verify($codeTokenString)
    {
        $jwtParts = explode('.', $codeTokenString);
        if (3 !== \count($jwtParts)) {
            return false;
        }

        $res = sodium_crypto_sign_verify_detached(
            Base64UrlSafe::decode($jwtParts[2]),
            $jwtParts[0].'.'.$jwtParts[1],
            $this->publicKey->raw()
        );

        if (false === $res) {
            return false;
        }

        return Json::decode(Base64UrlSafe::decode($jwtParts[1]));
    }
}
