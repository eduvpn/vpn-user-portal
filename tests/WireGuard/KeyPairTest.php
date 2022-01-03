<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\WireGuard\KeyPair;

/**
 * @internal
 * @coversNothing
 */
final class KeyPairTest extends TestCase
{
    /**
     * We expect the computePublicKey function to result in the same public key
     * as the wireguard-tools "wg" command.
     */
    public function testWgCompare(): void
    {
        // wg genkey
        $secretKey = '4Hz0EbDvEgsv86ZaNaoiB7cTSCMm7/YgnfN5ZB4HE2E=';
        // echo 4Hz0EbDvEgsv86ZaNaoiB7cTSCMm7/YgnfN5ZB4HE2E= | wg pubkey
        $publicKey = 'e0oG2NgyyXli4Chu7bP5ZnFfFaG2KOqcAIIzAg2Lojk=';

        static::assertSame($publicKey, KeyPair::computePublicKey($secretKey));
    }

    /**
     * We expect the public key that is in the output of the generate() call to
     * be identical to the public key derived from the secret key.
     */
    public function testGenerateAndComputeCompare(): void
    {
        $keyPair = KeyPair::generate();
        static::assertSame($keyPair['public_key'], KeyPair::computePublicKey($keyPair['secret_key']));
    }
}
