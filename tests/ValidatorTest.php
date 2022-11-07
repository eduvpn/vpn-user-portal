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
use RangeException;
use Vpn\Portal\Validator;

/**
 * @internal
 *
 * @coversNothing
 */
final class ValidatorTest extends TestCase
{
    public function testInvalidPublicKey(): void
    {
        $this->expectException(RangeException::class);
        Validator::publicKey('EtPxdnGP+KCS7tVBohNgt5lcXF7XubTDKr6QdwuyGU=');
    }

    public function testDisplayNameNonUtf(): void
    {
        $this->expectException(RangeException::class);
        Validator::displayName(mb_convert_encoding('€', 'utf-16', 'utf-8'));
    }

    public function testDisplayNameTooShort(): void
    {
        $this->expectException(RangeException::class);
        Validator::displayName('');
    }

    public function testDisplayNameTooLong(): void
    {
        $this->expectException(RangeException::class);
        Validator::displayName(str_repeat('€', 65));
    }
}
