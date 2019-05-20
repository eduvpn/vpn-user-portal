<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use DateTime;
use LC\Portal\Config\PortalConfig;
use LC\Portal\Http\AdminHook;
use LC\Portal\Http\AdminPortalModule;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\Storage;
use LC\Portal\Tpl;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminPortalModuleTest extends TestCase
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
        $storage->addCertificate(
            'bar',
            'CN_1',
            'User Certificate',
            new DateTime('2019-01-01'),
            new Datetime('2019-02-01'),
            null // not an OAuth client
        );

        $portalConfig = PortalConfig::fromFile(\dirname(\dirname(__DIR__)).'/config/config.php.example');
        $tpl = new Tpl([\dirname(\dirname(__DIR__)).'/views']);
        $tpl->addDefault(
            [
                'requestRoot' => '/',
                'supportedLanguages' => ['en_US' => 'English', 'nl_NL' => 'Nederlands'],
            ]
        );
        $adminPortalModule = new AdminPortalModule(
            '/tmp', // XXX get rid of this, use "$statsData" or something
            $portalConfig,
            $tpl,
            $storage,
            new TestServerManager()
        );
        $this->service = new Service();
        $this->service->addBeforeHook('auth', new TestAuthenticationHook('foo'));
        $this->service->addBeforeHook('is_admin', new AdminHook([], ['foo'], $tpl));
        $adminPortalModule->init($this->service);
    }

    /**
     * @return void
     */
    public function testConnections()
    {
        $request = new Request(
            [
                'REQUEST_METHOD' => 'GET',
                'SERVER_NAME' => 'vpn.example.org',
                'SERVER_PORT' => 443,
                'REQUEST_URI' => '/index.php/connections',
                'SCRIPT_NAME' => '/index.php',
            ],
            [],
            []
        );

        $httpResponse = $this->service->run($request);
        $this->assertSame(200, $httpResponse->getStatusCode());
        $this->assertSame(['Content-Type' => 'text/html'], $httpResponse->getHeaders());
        //file_put_contents(__DIR__.'/connections.html', $httpResponse->getBody());
        $this->assertSame(file_get_contents(__DIR__.'/connections.html'), $httpResponse->getBody());
    }
}
