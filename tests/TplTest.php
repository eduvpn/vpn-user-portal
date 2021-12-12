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
use Vpn\Portal\Tpl;

/**
 * @internal
 * @coversNothing
 */
final class TplTest extends TestCase
{
    public function testToHuman(): void
    {
        static::assertSame('0 B', Tpl::toHuman(0));
        static::assertSame('1023 B', Tpl::toHuman(1023));
        static::assertSame('1.00 KiB', Tpl::toHuman(1024));
        static::assertSame('1.50 KiB', Tpl::toHuman(1024 + 512));
        static::assertSame('2.00 KiB', Tpl::toHuman(2048));
        static::assertSame('1.00 GiB', Tpl::toHuman(1024 * 1024 * 1024));
        static::assertSame('512.00 KiB', Tpl::toHuman(1024 * 512));
        static::assertSame('1.15 GiB', Tpl::toHuman(1234567890));
    }
}
