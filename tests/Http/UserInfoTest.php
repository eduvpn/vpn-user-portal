<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\Http\UserInfo;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserInfoTest extends TestCase
{
    public function testNoExpiry(): void
    {
        $userInfo = new UserInfo(
            'foo',
            []
        );
        $this->assertSame(0, count($userInfo->sessionExpiry()));
    }

    public function testOneExpiry(): void
    {
        $userInfo = new UserInfo(
            'foo',
            [
                'https://eduvpn.org/expiry#P1Y',
            ]
        );
        $this->assertSame(
            [
                'P1Y',
            ],
            $userInfo->sessionExpiry()
        );
    }

    public function testMultipleExpiries(): void
    {
        $userInfo = new UserInfo(
            'foo',
            [
                'https://eduvpn.org/expiry#P1Y',
                'https://eduvpn.org/expiry#PT12H',
            ]
        );
        $this->assertSame(
            [
                'P1Y',
                'PT12H',
            ],
            $userInfo->sessionExpiry()
        );
    }
}
