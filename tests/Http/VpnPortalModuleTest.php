<?php

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
use LC\Portal\Http\AdminHook;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\Http\VpnPortalModule;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OpenVpn\TlsCrypt;
use LC\Portal\Storage;
use LC\Portal\Tests\TestCa;
use LC\Portal\Tpl;
use PDO;
use PHPUnit\Framework\TestCase;

class VpnPortalModuleTest extends TestCase
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
        $storage->updateSessionInfo('foo', date_add(new DateTime('2019-01-01'), new DateInterval('P90D')), []);
        $tpl = new Tpl([\dirname(\dirname(__DIR__)).'/views']);
        $tpl->addDefault(
            [
                'requestRoot' => '/',
                'supportedLanguages' => ['en_US' => 'English', 'nl_NL' => 'Nederlands'],
            ]
        );
        $portalConfig = PortalConfig::fromFile(\dirname(\dirname(__DIR__)).'/config/config.php.example');
        $vpnPortalModule = new VpnPortalModule(
            $portalConfig,
            $tpl,
            new TestSession(),
            $storage,
            new TestCa(new DateTime('2019-01-01')),
            TlsCrypt::fromFile(\dirname(__DIR__).'/tls-crypt.key'),
            new TestServerManager(),
            new ClientDb()
        );
        $vpnPortalModule->setRandom(new TestRandom());
        $vpnPortalModule->setDateTime(new DateTime('2019-01-01'));
        $this->service = new Service();
        $this->service->addBeforeHook('auth', new TestAuthenticationHook('foo'));
        $this->service->addBeforeHook('is_admin', new AdminHook([], ['admin'], $tpl));
        $vpnPortalModule->init($this->service);
    }

    /**
     * @return void
     */
    public function testNew()
    {
        $request = new Request(
            [
                'REQUEST_METHOD' => 'GET',
                'SERVER_NAME' => 'vpn.example.org',
                'SERVER_PORT' => '443',
                'REQUEST_URI' => '/index.php/new',
                'SCRIPT_NAME' => '/index.php',
            ],
            [],
            []
        );

        $httpResponse = $this->service->run($request);
        $this->assertSame(200, $httpResponse->getStatusCode());
        $this->assertSame(['Content-Type' => 'text/html'], $httpResponse->getHeaders());
        //file_put_contents(__DIR__.'/new.html', $httpResponse->getBody());
        $this->assertSame(file_get_contents(__DIR__.'/new.html'), $httpResponse->getBody());
    }

    /**
     * @return void
     */
    public function testPostNew()
    {
        $request = new Request(
            [
                'REQUEST_METHOD' => 'POST',
                'SERVER_NAME' => 'vpn.example.org',
                'SERVER_PORT' => '443',
                'REQUEST_URI' => '/index.php/new',
                'SCRIPT_NAME' => '/index.php',
            ],
            [],
            [
                'displayName' => 'Test',
                'profileId' => 'default',
            ]
        );

        $httpResponse = $this->service->run($request);
        $this->assertSame(200, $httpResponse->getStatusCode());
        $this->assertSame(
            [
                'Content-Type' => 'application/x-openvpn-profile',
                'Content-Disposition' => 'attachment; filename="vpn.example.org_default_20190101_Test.ovpn"',
            ],
            $httpResponse->getHeaders()
        );
        //file_put_contents(__DIR__.'/client.conf', $httpResponse->getBody());
        $this->assertSame(file_get_contents(__DIR__.'/client.conf'), $httpResponse->getBody());
    }
}
