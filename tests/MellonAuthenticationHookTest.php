<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Common\Config;
use LC\Common\Http\Request;
use LC\Portal\MellonAuthentication;
use PHPUnit\Framework\TestCase;

class MellonAuthenticationHookTest extends TestCase
{
    public function testBasic()
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
        $this->assertSame('abcdef', $userInfo->getUserId());
        $this->assertSame([], $userInfo->getPermissionList());
    }

    public function testSerialization()
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
        $this->assertSame('https://idp.example.org/saml!https://sp.example.org/saml!abcdef', $userInfo->getUserId());
        $this->assertSame([], $userInfo->getPermissionList());
    }

    public function testPermissionList()
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
        $this->assertSame('abcdef', $userInfo->getUserId());
        $this->assertSame(['a', 'b'], $userInfo->getPermissionList());
    }
}
