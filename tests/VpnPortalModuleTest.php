<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use DateTimeImmutable;
use LC\Portal\Config;
use LC\Portal\Http\NullAuthenticationHook;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OpenVpn\DaemonSocket;
use LC\Portal\OpenVpn\DaemonWrapper;
use LC\Portal\Storage;
use LC\Portal\TlsCrypt;
use LC\Portal\VpnPortalModule;
use PDO;
use PHPUnit\Framework\TestCase;

class VpnPortalModuleTest extends TestCase
{
    /** @var \LC\Portal\Http\Service */
    private $service;

    protected function setUp(): void
    {
        $schemaDir = \dirname(__DIR__).'/schema';
        $storage = new Storage(new PDO('sqlite::memory:'), $schemaDir);
        $storage->init();

        $tmpDir = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(10)));
        mkdir($tmpDir);

        $config = new Config(
            [
                'vpnProfiles' => [
                    'default' => [
                        'displayName' => 'Default',
                    ],
                ],
                'sessionExpiry' => 'P90D',
            ]
        );

        $daemonSocket = new DaemonSocket($tmpDir, false);
        $daemonWrapper = new DaemonWrapper($config, $storage, $daemonSocket);

        $vpnPortalModule = new VpnPortalModule(
            $config,
            new JsonTpl(),
            new TestSession(),
            $daemonWrapper,
            $storage,
            new TlsCrypt($tmpDir),
            new TestRandom(),
            new TestCa(),
            new ClientDb()
        );
        $vpnPortalModule->setDateTime(new DateTimeImmutable('2019-01-01'));
        $this->service = new Service();
        $this->service->addModule($vpnPortalModule);
        $this->service->addBeforeHook('auth', new NullAuthenticationHook('foo'));
    }

    public function testHomeGet(): void
    {
        $this->assertSame(
            [
                'vpnPortalHome' => [],
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
     * @return \LC\Portal\Http\Response|array
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
