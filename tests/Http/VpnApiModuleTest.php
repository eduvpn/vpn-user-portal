<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use DateInterval;
use DateTime;
use LC\Portal\Config\PortalConfig;
use LC\Portal\Http\BearerAuthenticationHook;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\Http\VpnApiModule;
use LC\Portal\OpenVpn\TlsCrypt;
use LC\Portal\Storage;
use LC\Portal\Tests\TestCa;
use PDO;
use PHPUnit\Framework\TestCase;

class VpnApiModuleTest extends TestCase
{
    /** @var \LC\Portal\Http\Service */
    private $service;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $storage = new Storage(new PDO('sqlite::memory:'), \dirname(\dirname(__DIR__)).'/schema');
        $storage->init();
        $storage->setDateTime(new DateTime('2019-01-01'));
        $storage->updateSessionInfo('foo', date_add(new DateTime('2019-01-01'), new DateInterval('P90D')), []);
        $vpnApiModule = new VpnApiModule(
            $storage,
            PortalConfig::fromFile(\dirname(\dirname(__DIR__)).'/config/config.php.example'),
            new TestCa(new DateTime('2019-01-01')),
            TlsCrypt::fromFile(\dirname(__DIR__).'/tls-crypt.key')
        );
        $vpnApiModule->setDateTime(new DateTime('2019-01-01'));
        $vpnApiModule->setRandom(new TestRandom());
        $this->service = new Service();
        $this->service->addBeforeHook('auth', new BearerAuthenticationHook(new TestBearerValidator()));
        $vpnApiModule->init($this->service);
    }

    /**
     * @return void
     */
    public function testProfileList()
    {
        $request = new Request(
            [
                'REQUEST_METHOD' => 'GET',
                'SERVER_NAME' => 'vpn.example.org',
                'SERVER_PORT' => '443',
                'REQUEST_URI' => '/api.php/profile_list',
                'SCRIPT_NAME' => '/api.php',
            ],
            [],
            []
        );

        $httpResponse = $this->service->run($request);
        $this->assertSame(200, $httpResponse->getStatusCode());
        $this->assertSame(['Content-Type' => 'application/json'], $httpResponse->getHeaders());
        $this->assertSame('{"profile_list":{"ok":true,"data":[{"profile_id":"default","display_name":"Default Profile","two_factor":false}]}}', $httpResponse->getBody());
    }

    public function testCreateKeypair()
    {
        $request = new Request(
            [
                'REQUEST_METHOD' => 'POST',
                'SERVER_NAME' => 'vpn.example.org',
                'SERVER_PORT' => '443',
                'REQUEST_URI' => '/api.php/create_keypair',
                'SCRIPT_NAME' => '/api.php',
            ],
            [],
            []
        );

        // XXX why does it not take P90D in consideration for expiresAt?!
        // It should be 90 days later than today...

        $httpResponse = $this->service->run($request);
        $this->assertSame(200, $httpResponse->getStatusCode());
        $this->assertSame(['Content-Type' => 'application/json'], $httpResponse->getHeaders());
        $this->assertSame('{"create_keypair":{"ok":true,"data":{"certificate":"---ClientCert [00000000000000000000000000000000,2019-04-01T00:00:00+00:00]---","private_key":"---ClientKey---"}}}', $httpResponse->getBody());

        // check the certificate
        $request = new Request(
            [
                'REQUEST_METHOD' => 'GET',
                'SERVER_NAME' => 'vpn.example.org',
                'SERVER_PORT' => '443',
                'REQUEST_URI' => '/api.php/check_certificate',
                'SCRIPT_NAME' => '/api.php',
            ],
            ['common_name' => '00000000000000000000000000000000'],
            []
        );

        $httpResponse = $this->service->run($request);
        $this->assertSame(200, $httpResponse->getStatusCode());
        $this->assertSame(['Content-Type' => 'application/json'], $httpResponse->getHeaders());
        $this->assertSame('{"check_certificate":{"ok":true,"data":{"is_valid":true}}}', $httpResponse->getBody());
    }
}
