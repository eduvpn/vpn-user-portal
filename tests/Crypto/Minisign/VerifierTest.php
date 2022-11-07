<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\Crypto\Minisign\Exception\MinisignException;
use Vpn\Portal\Crypto\Minisign\PublicKey;
use Vpn\Portal\Crypto\Minisign\Verifier;

/**
 * @internal
 *
 * @coversNothing
 */
final class VerifierTest extends TestCase
{
    public function testVerify(): void
    {
        $signatureVerifier = new Verifier(
            [
                new PublicKey('RWTVgglgaFs9Ls8gVGjtD3tPSAtLQmOSaMnmxJ9j1b3BsfxjRYae5b/W'),
            ]
        );

        static::assertTrue(
            $signatureVerifier->verifyDetached(
                file_get_contents(__DIR__.'/minisign.pub'),
                file_get_contents(__DIR__.'/minisign.pub.minisig')
            )
        );
    }

    public function testVerifyWrongAlgo(): void
    {
        $signatureVerifier = new Verifier(
            [
                new PublicKey('RWTVgglgaFs9Ls8gVGjtD3tPSAtLQmOSaMnmxJ9j1b3BsfxjRYae5b/W'),
            ]
        );

        $this->expectException(MinisignException::class);
        $this->expectExceptionMessage('signature has invalid algorithm');
        $signatureVerifier->verifyDetached(
            file_get_contents(__DIR__.'/minisign.pub'),
            file_get_contents(__DIR__.'/minisign.pub.minihsig')
        );
    }
}
