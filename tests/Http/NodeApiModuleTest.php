<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ConnectionHooks;
use Vpn\Portal\Http\NodeApiModule;
use Vpn\Portal\Http\NodeApiService;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\UserInfo;
use Vpn\Portal\NullLogger;
use Vpn\Portal\OpenVpn\ServerConfig as OpenVpnServerConfig;
use Vpn\Portal\OpenVpn\TlsCrypt;
use Vpn\Portal\ServerConfig;
use Vpn\Portal\Storage;
use Vpn\Portal\WireGuard\ServerConfig as WireGuardServerConfig;

/**
 * @internal
 *
 * @coversNothing
 */
final class NodeApiModuleTest extends TestCase
{
    private Config $config;
    private NodeApiService $service;
    private Storage $storage;
    private DateTimeImmutable $dateTime;

    protected function setUp(): void
    {
        $this->config = new Config(
            [
                'Db' => [
                    'dbDsn' => 'sqlite::memory:',
                ],
                'ProfileList' => [
                    [
                        'profileId' => 'default',
                        'displayName' => 'Default (Prefer OpenVPN)',
                        'hostName' => 'vpn.example',
                        'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                        'wRangeFour' => '10.43.43.0/24',
                        'wRangeSix' => 'fd43::/64',
                        'oRangeFour' => '10.42.42.0/24',
                        'oRangeSix' => 'fd42::/64',
                        'aclPermissionList' => ['foo'],
                    ],
                    [
                        'profileId' => 'default-wg',
                        'displayName' => 'Default (Prefer WireGuard)',
                        'hostName' => 'vpn.example',
                        'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                        'wRangeFour' => '10.44.44.0/29',
                        'wRangeSix' => 'fd44::/64',
                        'oRangeFour' => '10.45.45.0/24',
                        'oRangeSix' => 'fd45::/64',
                        'preferredProto' => 'wireguard',
                        'aclPermissionList' => ['bar'],
                    ],
                ],
            ]
        );
        $this->dateTime = new DateTimeImmutable('2022-01-01T09:00:00+00:00');
        $baseDir = \dirname(__DIR__, 2);
        $this->storage = new Storage($this->config->dbConfig($baseDir));
        $this->storage->userAdd(new UserInfo('user_id', ['foo']), $this->dateTime);
        $this->storage->oCertAdd('user_id', 0, 'default', 'btQ/mhN7ecbCFVLH2tBiM+7MhZFkFObrmHlkAc1B9W4=', 'test', $this->dateTime, $this->dateTime->add(new DateInterval('P90D')), null);
        $this->storage->oCertAdd('user_id', 0, 'default-wg', 'KdNTh+t98gnsV0gXmG7sFzXL5Wk/OQrerWqg+IF0dOg=', 'test', $this->dateTime, $this->dateTime->add(new DateInterval('P90D')), null);
        $nodeApiModule = new NodeApiModule(
            $this->config,
            $this->storage,
            new ServerConfig(
                new OpenVpnServerConfig(new TestCa(), new TlsCrypt($baseDir.'/data/keys')),
                new WireGuardServerConfig($baseDir.'/data/keys', $this->config->wireGuardConfig()->listenPort()),
            ),
            new ConnectionHooks(new NullLogger()),
            new NullLogger()
        );
        $this->service = new NodeApiService(
            new DummyAuthModule()
        );
        $this->service->addModule($nodeApiModule);
    }

    public function testConnectSuccess(): void
    {
        $request = new Request(
            [
                'REQUEST_URI' => '/connect',
                'REQUEST_METHOD' => 'POST',
                'HTTP_X_NODE_NUMBER' => '0',
            ],
            [],
            [
                'profile_id' => 'default',
                'common_name' => 'btQ/mhN7ecbCFVLH2tBiM+7MhZFkFObrmHlkAc1B9W4=',
                'originating_ip' => '10.0.0.5',
                'ip_four' => '10.43.43.2',
                'ip_six' => 'fd43::2',
            ],
            []
        );

        static::assertSame(
            'OK',
            $this->service->run($request)->responseBody()
        );
    }

    public function testConnectFailNoPermission(): void
    {
        $request = new Request(
            [
                'REQUEST_URI' => '/connect',
                'REQUEST_METHOD' => 'POST',
                'HTTP_X_NODE_NUMBER' => '0',
            ],
            [],
            [
                'profile_id' => 'default-wg',
                'common_name' => 'KdNTh+t98gnsV0gXmG7sFzXL5Wk/OQrerWqg+IF0dOg=',
                'originating_ip' => '10.0.0.5',
                'ip_four' => '10.45.45.2',
                'ip_six' => 'fd45::2',
            ],
            []
        );

        static::assertSame(
            'ERR',
            $this->service->run($request)->responseBody()
        );
    }
}
