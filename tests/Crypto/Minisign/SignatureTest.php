<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\Crypto\Minisign\Signature;

/**
 * @internal
 *
 * @coversNothing
 */
final class SignatureTest extends TestCase
{
    public function testSignatureFromFile(): void
    {
        $s = Signature::fromFile(__DIR__.'/minisign.pub.minisig');
        static::assertSame('d5820960685b3d2e', bin2hex($s->keyId()));
        static::assertSame('8a78784df08706ff5e9ec85cbe25659db41a6db4c94544ffce704efdd3249191fc8a72f1162422100b862c70b7881b24bfd8764f677ad56a299294591ffb2402', bin2hex($s->raw()));
    }

    public function testSignatureFromString(): void
    {
        $s = Signature::fromString(file_get_contents(__DIR__.'/minisign.pub.minisig'));
        static::assertSame('d5820960685b3d2e', bin2hex($s->keyId()));
        static::assertSame('8a78784df08706ff5e9ec85cbe25659db41a6db4c94544ffce704efdd3249191fc8a72f1162422100b862c70b7881b24bfd8764f677ad56a299294591ffb2402', bin2hex($s->raw()));
    }
}
