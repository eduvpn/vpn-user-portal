<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use DateInterval;
use DateTimeImmutable;
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use fkooman\OAuth\Server\Scope;
use PHPUnit\Framework\TestCase;
use Vpn\Portal\Config;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\Http\ApiService;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\VpnApiThreeModule;
use Vpn\Portal\NullLogger;
use Vpn\Portal\OpenVpn\TlsCrypt;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;
use Vpn\Portal\VpnDaemon;

/**
 * @internal
 * @coversNothing
 */
final class VpnApiThreeModuleTest extends TestCase
{
    private ApiService $service;

    protected function setUp(): void
    {
        $dateTime = new DateTimeImmutable();
        $tmpDir = sprintf('%s/vpn-user-portal-%s', sys_get_temp_dir(), bin2hex(random_bytes(32)));
        mkdir($tmpDir);
        copy(\dirname(__DIR__).'/data/tls-crypt-default.key', $tmpDir.'/tls-crypt-default.key');

        $baseDir = \dirname(__DIR__, 2);
        $config = new Config(
            [
                'Db' => [
                    'dbDsn' => 'sqlite::memory:',
                ],
                'ProfileList' => [
                    [
                        'profileId' => 'default',
                        'displayName' => 'Default',
                        'hostName' => 'vpn.example',
                        'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                        'wRangeFour' => '10.43.43.0/24',
                        'wRangeSix' => 'fd43::/64',
                        'oRangeFour' => '10.42.42.0/24',
                        'oRangeSix' => 'fd42::/64',
                    ],
                ],
            ]
        );

        $storage = new Storage($config->dbConfig($baseDir));

        // XXX the user & authorization MUST exist apparently, this will NOT work with guest usage!
        $storage->userAdd('user_id', $dateTime, []);
        $oauthStorage = new OAuthStorage($storage->dbPdo(), 'oauth_');
        $oauthStorage->storeAuthorization('user_id', 'client_id', new Scope('config'), 'auth_key', $dateTime, $dateTime->add(new DateInterval('P90D')));

        $apiModule = new VpnApiThreeModule(
            $config,
            $storage,
            new ServerInfo(
                $tmpDir,
                new TestCa(),
                new TlsCrypt($tmpDir),
                $config->wireGuardConfig()->listenPort(),
                'gc6RjjPtIKeflbOun+dyAssnsdXzD6bmWisbxJrZiB0=',
            ),
            new ConnectionManager(
                $config,
                new VpnDaemon(
                    new TestHttpClient(),
                    new NullLogger()
                ),
                $storage,
                new NullLogger()
            )
        );
        $this->service = new ApiService(new TestValidator());
        $this->service->addModule($apiModule);
    }

    public function testInfo(): void
    {
        $request = new Request(
            [
                'REQUEST_URI' => '/v3/info',
                'REQUEST_METHOD' => 'GET',
            ],
            [],
            [],
            []
        );

        static::assertSame('{"info":{"profile_list":[{"profile_id":"default","display_name":"Default","vpn_proto_list":["openvpn","wireguard"],"vpn_proto_preferred":"openvpn","default_gateway":true}]}}', $this->service->run($request)->responseBody());
    }

    public function testConnect(): void
    {
        $request = new Request(
            [
                'REQUEST_URI' => '/v3/connect',
                'REQUEST_METHOD' => 'POST',
            ],
            [],
            [
                'profile_id' => 'default',
            ],
            []
        );

        static::assertSame(
            trim(
                file_get_contents(\dirname(__DIR__).'/data/expected_api_connect_response.txt')
            ),
            $this->service->run($request)->responseBody()
        );
    }
}
