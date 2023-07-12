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
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\Http\Auth\NullAuthModule;
use Vpn\Portal\Http\NullSession;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\UpdateUserInfoHook;
use Vpn\Portal\Http\UserInfo;
use Vpn\Portal\StaticPermissionSource;
use Vpn\Portal\Storage;

/**
 * @internal
 *
 * @coversNothing
 */
class UpdateUserInfoHookTest extends TestCase
{
    private UpdateUserInfoHook $updateUserInfoHook;

    public function setUp(): void
    {
        $baseDir = \dirname(__DIR__, 2);
        $config = new Config(
            [
                'Db' => [
                    'dbDsn' => 'sqlite::memory:',
                ],
            ]
        );
        $storage = new Storage($config->dbConfig($baseDir));
        $this->updateUserInfoHook = new UpdateUserInfoHook(
            new NullSession(),
            $storage,
            new NullAuthModule(),
            [
                new StaticPermissionSource(__DIR__.'/data/static_permissions.json'),
            ]
        );
    }

    public function testStaticPermissionSource(): void
    {
        $userInfo = new UserInfo('foo', []);
        $this->updateUserInfoHook->afterAuth(
            new Request([], [], [], []),
            $userInfo
        );

        $this->assertSame(['example-permission'], $userInfo->permissionList());
    }

    public function testStaticPermissionSourceNoPermission(): void
    {
        $userInfo = new UserInfo('baz', []);
        $this->updateUserInfoHook->afterAuth(
            new Request([], [], [], []),
            $userInfo
        );

        $this->assertSame([], $userInfo->permissionList());
    }

    public function testStaticPermissionSourceExistingPermission(): void
    {
        $userInfo = new UserInfo('foo', ['p1', 'p2']);
        $this->updateUserInfoHook->afterAuth(
            new Request([], [], [], []),
            $userInfo
        );

        $this->assertSame(['p1', 'p2', 'example-permission'], $userInfo->permissionList());
    }
}
