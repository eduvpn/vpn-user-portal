<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\WireGuard\KeyPair;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class KeyPairTest extends TestCase
{
    public function testWgCompare(): void
    {
        // wg genkey
        $secretKey = '4Hz0EbDvEgsv86ZaNaoiB7cTSCMm7/YgnfN5ZB4HE2E=';
        // echo 4Hz0EbDvEgsv86ZaNaoiB7cTSCMm7/YgnfN5ZB4HE2E= | wg pubkey
        $publicKey = 'e0oG2NgyyXli4Chu7bP5ZnFfFaG2KOqcAIIzAg2Lojk=';

        static::assertSame($publicKey, KeyPair::computePublicKey($secretKey));
    }

    public function testGenerateCompute()
    {
        $keyPair = KeyPair::generate();
        static::assertSame($keyPair['public_key'], KeyPair::computePublicKey($keyPair['secret_key']));
    }
}