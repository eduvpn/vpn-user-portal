<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use DateInterval;
use DateTime;
use LC\Common\Config;
use LC\Common\Http\NullAuthenticationHook;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\HttpClient\ServerClient;
use LC\Portal\ClientFetcher;
use LC\Portal\Storage;
use LC\Portal\VpnPortalModule;
use PDO;
use PHPUnit\Framework\TestCase;

class VpnPortalModuleTest extends TestCase
{
    /** @var \LC\Common\Http\Service */
    private $service;

    protected function setUp(): void
    {
        $schemaDir = \dirname(__DIR__).'/schema';
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');
        $storage = new Storage(new PDO('sqlite::memory:'), $schemaDir, new DateInterval('P90D'));
        $storage->init();

        $vpnPortalModule = new VpnPortalModule(
            new Config(['sessionExpiry' => 'P90D']),
            new JsonTpl(),
            $serverClient,
            new TestSession(),
            $storage,
            new ClientFetcher(new Config(['Api' => []]))
        );
        $vpnPortalModule->setDateTime(new DateTime('2019-01-01'));
        $this->service = new Service();
        $this->service->addModule($vpnPortalModule);
        $this->service->addBeforeHook('auth', new NullAuthenticationHook('foo'));
    }

    public function testHomeGet(): void
    {
        $this->assertSame(
            [
                'vpnPortalHome' => [
                    'motdMessage' => [
                        'id' => 1,
                        'message_type' => 'motd',
                        'message_body' => 'Hello World!',
                    ],
                ],
            ],
            $this->makeRequest('GET', '/home')
        );
    }

    public function testConfigurtionsPost(): void
    {
        $this->assertSame(
            trim(file_get_contents(sprintf('%s/data/foo_MyConfig.ovpn', __DIR__))),
            $this->makeRequest(
                'POST',
                '/configurations',
                [],
                ['displayName' => 'MyConfig', 'profileId' => 'internet'],
                true
            )->getBody()
        );
    }

    public function testAccount(): void
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
                    'userMessages' => [],
                    'userConnectionLogEntries' => [],
                    'idNameMapping' => [
                        'internet' => 'Internet Access',
                    ],
                ],
            ],
            $this->makeRequest('GET', '/account')
        );
    }

    public function testConfigurations(): void
    {
        $this->assertSame(
            [
                'vpnPortalConfigurations' => [
                    'expiryDate' => '2019-04-01',
                    'profileList' => [
                        'internet' => [
                            'displayName' => 'Internet Access',
                        ],
                    ],
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
            $this->makeRequest('GET', '/configurations')
        );
    }

    public function testDisableConfirm(): void
    {
        $this->assertSame(
            302,
            $this->makeRequest('POST', '/deleteCertificate', [], ['commonName' => '12345678901234567890123456789012'], true)->getStatusCode()
        );
    }

    /**
     * @param string               $requestMethod
     * @param string               $pathInfo
     * @param array<string,string> $getData
     * @param array<string,string> $postData
     * @param bool                 $returnResponseObj
     *
     * @return \LC\Common\Http\Response|array
     */
    private function makeRequest($requestMethod, $pathInfo, array $getData = [], array $postData = [], $returnResponseObj = false)
    {
        $response = $this->service->run(
            new Request(
                [
                    'SERVER_PORT' => '80',
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
