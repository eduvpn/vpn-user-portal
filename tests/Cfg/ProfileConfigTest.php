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
use Vpn\Portal\Cfg\ProfileConfig;

/**
 * @internal
 *
 * @coversNothing
 */
final class ProfileConfigTest extends TestCase
{
    public function testRangeSix(): void
    {
        $p = new ProfileConfig(
            [
                'nodeUrl' => ['http://a.vpn.example.org:41194', 'http://b.vpn.example.org:41194'],
                'wRangeSix' => ['10.0.0.0/24', '10.0.1.0/24'],
            ]
        );

        static::assertSame('10.0.0.0/24', (string) $p->wRangeSix(0));
        static::assertSame('10.0.1.0/24', (string) $p->wRangeSix(1));
    }
}
