<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Crypto\Minisign;

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
        // when/if implementing "hashed" version, we need
        // sodium_crypto_generichash($plainText, '', 64)
        $signatureObj = Signature::fromString($signatureString);
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
}
