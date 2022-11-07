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

/**
 * @internal
 *
 * @coversNothing
 */
final class PublicKeyTest extends TestCase
{
    public function testPublicKey(): void
    {
        // 00000000  45 64 d5 82 09 60 68 5b  3d 2e cf 20 54 68 ed 0f  |Ed?..`h[=.? Th?.|
        // 00000010  7b 4f 48 0b 4b 42 63 92  68 c9 e6 c4 9f 63 d5 bd  |{OH.KBc.h???.cս|
        // 00000020  c1 b1 fc 63 45 86 9e e5  bf d6                    |???cE..??|
        // 0000002a
        $p = new PublicKey('RWTVgglgaFs9Ls8gVGjtD3tPSAtLQmOSaMnmxJ9j1b3BsfxjRYae5b/W');
        static::assertSame('d5820960685b3d2e', bin2hex($p->keyId()));
        static::assertSame('cf205468ed0f7b4f480b4b42639268c9e6c49f63d5bdc1b1fc6345869ee5bfd6', bin2hex($p->raw()));
    }

    public function testPublicKeyFromFile(): void
    {
        $p = PublicKey::fromFile(__DIR__.'/minisign.pub');
        static::assertSame('d5820960685b3d2e', bin2hex($p->keyId()));
        static::assertSame('cf205468ed0f7b4f480b4b42639268c9e6c49f63d5bdc1b1fc6345869ee5bfd6', bin2hex($p->raw()));
    }

    public function testPublicKeyFromWrongFile(): void
    {
        $this->expectException(MinisignException::class);
        $this->expectExceptionMessage('public key has invalid length');
        $p = PublicKey::fromFile(__FILE__);
    }
}
