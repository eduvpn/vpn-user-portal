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
use Vpn\Portal\MellonAuthentication;

/**
 * @internal
 * @coversNothing
 */
final class MellonAuthenticationHookTest extends TestCase
{
    public function testBasic(): void
    {
        $config = new Config(
            [
                'userIdAttribute' => 'MELLON_urn:oid:1_3_6_1_4_1_5923_1_1_1_10',
            ]
        );
        $authHook = new MellonAuthentication($config);
        $userInfo = $authHook->executeBefore(
            new Request(
                [
                    'MELLON_urn:oid:1_3_6_1_4_1_5923_1_1_1_10' => 'abcdef',
                ]
            ),
            []
        );
        static::assertSame('abcdef', $userInfo->getUserId());
        static::assertSame([], $userInfo->getPermissionList());
    }

    public function testSerialization(): void
    {
        $config = new Config(
            [
                'userIdAttribute' => 'MELLON_urn:oid:1_3_6_1_4_1_5923_1_1_1_10',
                'nameIdSerialization' => true,
                'spEntityId' => 'https://sp.example.org/saml',
            ]
        );
        $authHook = new MellonAuthentication($config);
        $userInfo = $authHook->executeBefore(
            new Request(
                [
                    'MELLON_IDP' => 'https://idp.example.org/saml',
                    'MELLON_urn:oid:1_3_6_1_4_1_5923_1_1_1_10' => 'abcdef',
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
                'userIdAttribute' => 'MELLON_urn:oid:1_3_6_1_4_1_5923_1_1_1_10',
                'permissionAttribute' => 'MELLON_urn:oid:1_3_6_1_4_1_5923_1_1_1_7',
            ]
        );
        $authHook = new MellonAuthentication($config);
        $userInfo = $authHook->executeBefore(
            new Request(
                [
                    'MELLON_urn:oid:1_3_6_1_4_1_5923_1_1_1_10' => 'abcdef',
                    'MELLON_urn:oid:1_3_6_1_4_1_5923_1_1_1_7' => 'a;b',
                ]
            ),
            []
        );
        static::assertSame('abcdef', $userInfo->getUserId());
        static::assertSame(['a', 'b'], $userInfo->getPermissionList());
    }
}
