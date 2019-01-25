<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\Tests;

use DateInterval;
use DateTime;
use LetsConnect\Common\Config;
use LetsConnect\Common\Http\NullAuthenticationHook;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\HttpClient\ServerClient;
use LetsConnect\Portal\ClientFetcher;
use LetsConnect\Portal\Storage;
use LetsConnect\Portal\VpnPortalModule;
use PDO;
use PHPUnit\Framework\TestCase;

class VpnPortalModuleTest extends TestCase
{
    /** @var \LetsConnect\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $schemaDir = \dirname(__DIR__).'/schema';
        $httpClient = new TestHttpClient();
        $serverClient = new ServerClient($httpClient, 'serverClient');
        $storage = new Storage(new PDO('sqlite::memory:'), $schemaDir, new DateTime());
        $storage->init();

        $vpnPortalModule = new VpnPortalModule(
            new Config([]),
            new JsonTpl(),
            $serverClient,
            new TestSession(),
            $storage,
            new DateInterval('P90D'),
            new ClientFetcher(new Config(['Api' => []]))
        );
        $vpnPortalModule->setShuffleHosts(false);

        $this->service = new Service();
        $this->service->addModule($vpnPortalModule);
        $this->service->addBeforeHook('auth', new NullAuthenticationHook('foo'));
    }

    public function testNewGet()
    {
        $this->assertSame(
            [
                'vpnPortalNew' => [
                    'profileList' => [
                        'internet' => [
                            'displayName' => 'Internet Access',
                        ],
                    ],
                    'motdMessage' => [
                        'id' => 1,
                        'message_type' => 'motd',
                        'message_body' => 'Hello World!',
                    ],
                ],
            ],
            $this->makeRequest('GET', '/new')
        );
    }

    public function testNewPost()
    {
        $this->assertSame(
            trim(file_get_contents(sprintf('%s/data/foo_MyConfig.ovpn', __DIR__))),
            $this->makeRequest(
                'POST',
                '/new',
                [],
                ['displayName' => 'MyConfig', 'profileId' => 'internet'],
                true
            )->getBody()
        );
    }

    public function testAccount()
    {
        $this->assertSame(
            [
                'vpnPortalAccount' => [
                    'hasTotpSecret' => false,
                    'userInfo' => [],
                    'userPermissions' => [],
                    'authorizedClients' => [],
                    'twoFactorMethods' => [
                        'totp',
                    ],
                ],
            ],
            $this->makeRequest('GET', '/account')
        );
    }

    public function testCertificates()
    {
        $this->assertSame(
            [
                'vpnPortalCertificates' => [
                    'userCertificateList' => [
                        [
                            'display_name' => 'Foo',
                            'valid_from' => 123456,
                            'valid_to' => 2345567,
                            'client_id' => null,
                        ],
                    ],
                ],
            ],
            $this->makeRequest('GET', '/certificates')
        );
    }

    public function testDisableConfirm()
    {
        $this->assertSame(
            302,
            $this->makeRequest('POST', '/deleteCertificate', [], ['commonName' => '12345678901234567890123456789012'], true)->getStatusCode()
        );
    }

    private function makeRequest($requestMethod, $pathInfo, array $getData = [], array $postData = [], $returnResponseObj = false)
    {
        $response = $this->service->run(
            new Request(
                [
                    'SERVER_PORT' => 80,
                    'SERVER_NAME' => 'vpn.example',
                    'REQUEST_METHOD' => $requestMethod,
                    'REQUEST_URI' => $pathInfo,
                    'SCRIPT_NAME' => '/index.php',
                ],
                $getData,
                $postData
            )
        );

        if ($returnResponseObj) {
            return $response;
        }

        $responseBody = $response->getBody();

        return json_decode($responseBody, true);
    }
}
