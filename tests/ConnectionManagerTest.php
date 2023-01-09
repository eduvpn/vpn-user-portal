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
use Vpn\Portal\Dt;
use Vpn\Portal\Http\UserInfo;
use Vpn\Portal\NullLogger;
use Vpn\Portal\Storage;
use Vpn\Portal\VpnDaemon;
use Vpn\Portal\WireGuard\Key;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConnectionManagerTest extends TestCase
{
    private TestHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new TestHttpClient();
    }

    public function testSync(): void
    {
        $dateTime = Dt::get();
        $config = new Config(
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
                ],
            ]
        );

        $baseDir = \dirname(__DIR__);
        $storage = new Storage($config->dbConfig($baseDir));
        $storage->userAdd(new UserInfo('user_id', []), $dateTime);
        $storage->wPeerAdd('user_id', 0, 'default', 'My Test', Key::publicKeyFromSecretKey(Key::generate()), '10.43.43.5', 'fd43::5', $dateTime, $dateTime->add($config->sessionExpiry()), null);

        $vpnDaemon = new VpnDaemon(
            $this->httpClient,
            new NullLogger()
        );
        $connectionManager = new TestConnectionManager($config, $vpnDaemon, $storage);
        $connectionManager->sync();

        // this is not yet super useful test, but the basis is there for more
        // extensive tests if we run into trouble
        static::assertCount(3, $this->httpClient->logData());
    }
}
