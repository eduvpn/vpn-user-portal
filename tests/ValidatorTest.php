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
use RangeException;
use Vpn\Portal\Validator;

/**
 * @internal
 * @coversNothing
 */
final class ValidatorTest extends TestCase
{
    public function testPublicKey(): void
    {
        Validator::publicKey('EtPxdnGP+KCSv7tVBohNgt5lcXF7XubTDKr6QdwuyGU=');
    }

    public function testInvalidPublicKey(): void
    {
        $this->expectException(RangeException::class);
        $this->expectExceptionMessage('invalid value for "publicKey"');
        Validator::publicKey('EtPxdnGP+KCS7tVBohNgt5lcXF7XubTDKr6QdwuyGU=');
    }
}
