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
use Vpn\Portal\Config;
use Vpn\Portal\Http\Request;
use Vpn\Portal\ShibAuthentication;

/**
 * @internal
 * @coversNothing
 */
final class ShibAuthenticationHookTest extends TestCase
{
    public function testBasic(): void
    {
        $config = new Config(
            [
                'userIdAttribute' => 'persistent-id',
            ]
        );
        $authHook = new ShibAuthentication($config);
        $userInfo = $authHook->executeBefore(
            new Request(
                [
                    'persistent-id' => 'https://idp.example.org/saml!https://sp.example.org/saml!abcdef',
                ]
            ),
            []
        );
        static::assertSame('https://idp.example.org/saml!https://sp.example.org/saml!abcdef', $userInfo->getUserId());
        static::assertSame([], $userInfo->getPermissionList());
    }

    public function testPermissionList(): void
    {
        $config = new Config(
            [
                'userIdAttribute' => 'persistent-id',
                'permissionAttribute' => 'entitlement',
            ]
        );
        $authHook = new ShibAuthentication($config);
        $userInfo = $authHook->executeBefore(
            new Request(
                [
                    'persistent-id' => 'https://idp.example.org/saml!https://sp.example.org/saml!abcdef',
                    'entitlement' => 'a;b',
                ]
            ),
            []
        );
        static::assertSame('https://idp.example.org/saml!https://sp.example.org/saml!abcdef', $userInfo->getUserId());
        static::assertSame(['a', 'b'], $userInfo->getPermissionList());
    }
}
