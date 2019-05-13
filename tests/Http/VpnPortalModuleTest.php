<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\CA\EasyRsaCa;
use LC\Portal\Config\PortalConfig;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\Http\VpnPortalModule;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OpenVpn\ServerManager;
use LC\Portal\OpenVpn\TlsCrypt;
use LC\Portal\Storage;
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
        $portalConfig = PortalConfig::fromFile(\dirname(\dirname(__DIR__)).'/config/config.php.example');
        $tmpDir = sys_get_temp_dir().'/'.bin2hex(random_bytes(16));
        mkdir($tmpDir);
        $ca = new EasyRsaCa(\dirname(\dirname(__DIR__)).'/easy-rsa', $tmpDir);
        $ca->init();
        $tlsCrypt = TlsCrypt::generate();

        $tpl = new TestTpl();
        $session = new TestSession();
        $serverManager = new ServerManager($portalConfig, new TestSocket());
        $clientDb = new ClientDb();

        $vpnPortalModule = new VpnPortalModule($portalConfig, $tpl, $session, $storage, $ca, $tlsCrypt, $serverManager, $clientDb);
        $this->service = new Service();
        $this->service->addBeforeHook('auth', new TestAuthenticationHook('foo'));
        $vpnPortalModule->init($this->service);
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
                'SERVER_PORT' => 443,
                'REQUEST_URI' => '/index.php/new',
                'SCRIPT_NAME' => '/index.php',
            ],
            [],
            []
        );

        $httpResponse = $this->service->run($request);
        $this->assertSame(200, $httpResponse->getStatusCode());
        $this->assertSame(['Content-Type' => 'text/html'], $httpResponse->getHeaders());
        $this->assertSame('{"vpnPortalNew":{"profileList":{"default":{"displayName":"Default Profile"}},"motdMessage":false}}', $httpResponse->getBody());
    }
}
