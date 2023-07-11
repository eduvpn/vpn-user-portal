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
            __DIR__.'/data/static_permissions.json',
            __DIR__.'/data/ip_source_permissions.json',
        );
    }

    public function testIpSourcePermissionsIpFour(): void
    {
        $userInfo = new UserInfo('foo', []);
        $this->updateUserInfoHook->afterAuth(
            new Request(
                [
                    'REMOTE_ADDR' => '10.5.5.0',
                ],
                [],
                [],
                []
            ),
            $userInfo
        );

        $this->assertSame(['ip_foo'], $userInfo->permissionList());
    }

    public function testIpSourcePermissionsIpSix(): void
    {
        $userInfo = new UserInfo('foo', []);
        $this->updateUserInfoHook->afterAuth(
            new Request(
                [
                    'REMOTE_ADDR' => 'fd99::1',
                ],
                [],
                [],
                []
            ),
            $userInfo
        );

        $this->assertSame(['ip_foo'], $userInfo->permissionList());
    }

    public function testIpSourcePermissionsNoPermission(): void
    {
        $userInfo = new UserInfo('foo', []);
        $this->updateUserInfoHook->afterAuth(
            new Request(
                [
                    'REMOTE_ADDR' => '1.1.1.1',
                ],
                [],
                [],
                []
            ),
            $userInfo
        );

        $this->assertSame([], $userInfo->permissionList());
    }
}
