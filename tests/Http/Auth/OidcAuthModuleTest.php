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
use Vpn\Portal\Cfg\OidcAuthConfig;
use Vpn\Portal\Http\Auth\OidcAuthModule;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\UserInfo;

class OidcAuthModuleTest extends TestCase
{
    public function testSimple(): void
    {
        $o = new OidcAuthModule(new OidcAuthConfig([]));
        $userInfo = $o->userInfo(
            new Request(
                [
                    'REMOTE_USER' => 'foo',
                ],
                [],
                [],
                []
            )
        );
        $this->assertInstanceOf(UserInfo::class, $userInfo);
        $this->assertSame('foo', $userInfo->userId());
    }

    public function testPermissions(): void
    {
        $o = new OidcAuthModule(
            new OidcAuthConfig(
                [
                    'permissionAttributeList' => ['groups'],
                ]
            )
        );
        $userInfo = $o->userInfo(
            new Request(
                [
                    'REMOTE_USER' => 'foo',
                    'OIDC_CLAIM_groups' => 'g1,g2',
                ],
                [],
                [],
                []
            )
        );
        $this->assertInstanceOf(UserInfo::class, $userInfo);
        $this->assertSame('foo', $userInfo->userId());
        $this->assertSame(['A!groups!g1', 'A!groups!g2'], $userInfo->rawPermissionList());
    }
}
