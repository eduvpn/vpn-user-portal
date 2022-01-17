<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use DateTimeImmutable;
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use fkooman\OAuth\Server\Scope;
use PHPUnit\Framework\TestCase;
use Vpn\Portal\Config;
use Vpn\Portal\Http\ApiService;
use Vpn\Portal\Http\Request;
use Vpn\Portal\NullLogger;
use Vpn\Portal\OpenVpn\TlsCrypt;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;
use Vpn\Portal\VpnDaemon;
use Vpn\Portal\WireGuard\Key;

/**
 * @internal
 * @coversNothing
 */
final class VpnApiThreeModuleTest extends TestCase
{
    private Config $config;
    private ApiService $service;
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
                    ],
                ],
            ]
        );

        $this->dateTime = new DateTimeImmutable('2022-01-01T09:00:00+00:00');
        $tmpDir = sprintf('%s/vpn-user-portal-%s', sys_get_temp_dir(), bin2hex(random_bytes(32)));
        mkdir($tmpDir);
        copy(\dirname(__DIR__).'/data/tls-crypt-default.key', $tmpDir.'/tls-crypt-default.key');
        copy(\dirname(__DIR__).'/data/wireguard.0.public.key', $tmpDir.'/wireguard.0.public.key');

        $baseDir = \dirname(__DIR__, 2);

        $this->storage = new Storage($this->config->dbConfig($baseDir));

        // XXX the user & authorization MUST exist apparently, this will NOT work with guest usage!
        $this->storage->userAdd('user_id', $this->dateTime, []);
        $oauthStorage = new OAuthStorage($this->storage->dbPdo(), 'oauth_');
        $oauthStorage->storeAuthorization('user_id', 'client_id', new Scope('config'), 'auth_key', $this->dateTime, $this->dateTime->add($this->config->sessionExpiry()));

        $apiModule = new TestVpnApiThreeModule(
            $this->config,
            $this->storage,
            new ServerInfo(
                $tmpDir,
                new TestCa(),
                new TlsCrypt($tmpDir),
                $this->config->wireGuardConfig()->listenPort(),
                'gc6RjjPtIKeflbOun+dyAssnsdXzD6bmWisbxJrZiB0=',
            ),
            new TestConnectionManager(
                $this->config,
                new VpnDaemon(
                    new TestHttpClient(),
                    new NullLogger()
                ),
                $this->storage,
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

        static::assertSame(
            '{"info":{"profile_list":[{"profile_id":"default","display_name":"Default (Prefer OpenVPN)","vpn_proto_list":["openvpn","wireguard"],"vpn_proto_preferred":"openvpn","default_gateway":true},{"profile_id":"default-wg","display_name":"Default (Prefer WireGuard)","vpn_proto_list":["openvpn","wireguard"],"vpn_proto_preferred":"wireguard","default_gateway":true}]}}',
            $this->service->run($request)->responseBody()
        );
    }

    public function testConnectMissingProfile(): void
    {
        $request = new Request(
            [
                'REQUEST_URI' => '/v3/connect',
                'REQUEST_METHOD' => 'POST',
            ],
            [],
            [
                'profile_id' => 'missing-profile',
                // we specify random pubic key here, it doesn't come back in
                // the WireGuard config anyway...
                'public_key' => Key::publicKeyFromSecretKey(Key::generate()),
            ],
            []
        );

        $httpResponse = $this->service->run($request);
        static::assertSame(400, $httpResponse->statusCode());
        static::assertSame('{"error":"profile not available"}', $httpResponse->responseBody());
    }

    public function testConnectInvalidProfileSyntax(): void
    {
        $request = new Request(
            [
                'REQUEST_URI' => '/v3/connect',
                'REQUEST_METHOD' => 'POST',
            ],
            [],
            [
                'profile_id' => 'invalid%profile',
                // we specify random pubic key here, it doesn't come back in
                // the WireGuard config anyway...
                'public_key' => Key::publicKeyFromSecretKey(Key::generate()),
            ],
            []
        );

        $httpResponse = $this->service->run($request);
        static::assertSame(400, $httpResponse->statusCode());
        static::assertSame('{"error":"invalid \"profile_id\" [invalid value for \"profileId\"]"}', $httpResponse->responseBody());
    }

    public function testConnectOpenVpn(): void
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
                file_get_contents(\dirname(__DIR__).'/data/expected_openvpn_client_config.txt')
            ),
            $this->service->run($request)->responseBody()
        );

        static::assertSame(
            [
                [
                    'user_id' => 'user_id',
                    'profile_id' => 'default',
                    'common_name' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                ],
            ],
            $this->storage->oCertListByAuthKey('auth_key')
        );
    }

    public function testDisconnectOpenVpn(): void
    {
        $this->storage->oCertAdd('user_id', 0, 'default', 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', 'display_name', $this->dateTime, $this->dateTime->add($this->config->sessionExpiry()), 'auth_key');
        static::assertSame(
            [
                [
                    'user_id' => 'user_id',
                    'profile_id' => 'default',
                    'common_name' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                ],
            ],
            $this->storage->oCertListByAuthKey('auth_key')
        );

        $request = new Request(
            [
                'REQUEST_URI' => '/v3/disconnect',
                'REQUEST_METHOD' => 'POST',
            ],
            [],
            [],
            []
        );

        static::assertSame(
            204,
            $this->service->run($request)->statusCode()
        );

        static::assertEmpty($this->storage->oCertListByAuthKey('auth_key'));
    }

    public function testConnectWireGuard(): void
    {
        $request = new Request(
            [
                'REQUEST_URI' => '/v3/connect',
                'REQUEST_METHOD' => 'POST',
            ],
            [],
            [
                'profile_id' => 'default-wg',
                // we specify random pubic key here, it doesn't come back in
                // the WireGuard config anyway...
                'public_key' => Key::publicKeyFromSecretKey(Key::generate()),
            ],
            []
        );

        static::assertSame(
            trim(
                file_get_contents(\dirname(__DIR__).'/data/expected_wireguard_client_config.txt')
            ),
            $this->service->run($request)->responseBody()
        );
    }

    public function testNoMoreAvailableWireGuardIp(): void
    {
        // use up all IPs so the client cannot get a WireGuard config
        for ($i = 2; $i < 7; ++$i) {
            $this->storage->wPeerAdd('user_id', 0, 'default-wg', 'My Test', Key::publicKeyFromSecretKey(Key::generate()), '10.44.44.'.$i, 'fd44::'.$i, $this->dateTime, $this->dateTime->add($this->config->sessionExpiry()), null);
        }

        $request = new Request(
            [
                'REQUEST_URI' => '/v3/connect',
                'REQUEST_METHOD' => 'POST',
            ],
            [],
            [
                'profile_id' => 'default-wg',
                'public_key' => Key::publicKeyFromSecretKey(Key::generate()),
            ],
            []
        );

        $httpResponse = $this->service->run($request);
        // as the profile also supports OpenVPN, it really should return an
        // OpenVPN config...
        // XXX implement this
        static::assertSame(500, $httpResponse->statusCode());
        static::assertSame('{"error":"/connect failed: no free IP address"}', $httpResponse->responseBody());
    }
}
