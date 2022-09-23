<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Crypto\Minisign;

use Vpn\Portal\Crypto\Minisign\Exception\MinisignException;
use Vpn\Portal\Crypto\VerifierInterface;

/**
 * Validate Signify/Minisign signatures with the "legacy" format, i.e. not
 * hashed first.
 *
 * @see https://jedisct1.github.io/minisign/
 */
class Verifier implements VerifierInterface
{
    /** @var array<PublicKey> */
    private array $publicKeyList;

    /**
     * @param array<PublicKey> $publicKeyList
     */
    public function __construct(array $publicKeyList)
    {
        $this->publicKeyList = $publicKeyList;
    }

    /**
     * Verify a detached signature.
     */
    public function verifyDetached(string $plainText, string $signatureString): bool
    {
        $signatureObj = Signature::fromString($signatureString);
        if ('Ed' !== $signatureObj->signatureAlgo()) {
            throw new MinisignException(sprintf('expected signature algorithm "Ed", got "%s"', $signatureObj->signatureAlgo()));
        }
        foreach ($this->publicKeyList as $publicKey) {
            if ($signatureObj->keyId() === $publicKey->keyId()) {
                // found the public key!
                return sodium_crypto_sign_verify_detached(
                    $signatureObj->raw(),
                    $plainText,
                    $publicKey->raw()
                );
            }
        }

        return false;
    }

//    /**
//     * Verify a detached signature (hashed).
//     */
//    public function verifyDetachedHashed(string $plainText, SignatureInterface $signatureObj): bool
//    {
//        if ('ED' !== $signatureObj->signatureAlgo()) {
//            throw new MinisignException(sprintf('expected signature algorithm "ED", got "%s"', $signatureObj->signatureAlgo()));
//        }

//        foreach ($this->publicKeyList as $publicKey) {
//            if ($signatureObj->keyId() === $publicKey->keyId()) {
//                // found the public key!
//                return sodium_crypto_sign_verify_detached(
//                    $signatureObj->raw(),
//                    sodium_crypto_generichash(
//                        $plainText,
//                        '',
//                        64
//                    ),
//                    $publicKey->raw()
//                );
//            }
//        }

//        return false;
//    }
}
