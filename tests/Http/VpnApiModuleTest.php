<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use DateInterval;
use LC\Portal\CA\EasyRsaCa;
use LC\Portal\Config;
use LC\Portal\Http\BearerAuthenticationHook;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\Http\VpnApiModule;
use LC\Portal\Storage;
use LC\Portal\TlsCrypt;
use PDO;
use PHPUnit\Framework\TestCase;

class VpnApiModuleTest extends TestCase
{
    /** @var \LC\Portal\Http\Service */
    private $service;

    /**
     * @return void
     */
    public function setUp()
    {
        $storage = new Storage(new PDO('sqlite::memory:'), \dirname(\dirname(__DIR__)).'/schema');
        $storage->init();
        $config = Config::fromFile(\dirname(\dirname(__DIR__)).'/config/config.php.example');
        $tmpDir = sys_get_temp_dir().'/'.bin2hex(random_bytes(16));
        mkdir($tmpDir);
        $ca = new EasyRsaCa(\dirname(\dirname(__DIR__)).'/easy-rsa', $tmpDir);
        $ca->init();
        $tlsCrypt = new TlsCrypt(__DIR__.'/data');
        $sessionExpiry = new DateInterval('P90D');
        $vpnApiModule = new VpnApiModule($storage, $config, $ca, $tlsCrypt, $sessionExpiry);
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
                'SERVER_PORT' => 443,
                'REQUEST_URI' => '/api.php/profile_list',
                'SCRIPT_NAME' => '/api.php',
            ],
            [],
            []
        );

        $httpResponse = $this->service->run($request);
        $this->assertSame(200, $httpResponse->getStatusCode());
        $this->assertSame(['Content-Type' => 'application/json'], $httpResponse->getHeaders());
        $this->assertSame('{"profile_list":{"ok":true,"data":[{"profile_id":"internet","display_name":"Internet Access","two_factor":false}]}}', $httpResponse->getBody());
    }
}
