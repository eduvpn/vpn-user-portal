<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace SURFnet\VPN\Portal;

require_once sprintf('%s/Test/JsonTpl.php', __DIR__);
require_once sprintf('%s/Test/TestHttpClient.php', __DIR__);
require_once sprintf('%s/Test/TestSession.php', __DIR__);

use SURFnet\VPN\Common\Http\NullAuthenticationHook;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\HttpClient\CaClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Portal\Test\JsonTpl;
use SURFnet\VPN\Portal\Test\TestHttpClient;
use SURFnet\VPN\Portal\Test\TestSession;
use PHPUnit_Framework_TestCase;

class VpnPortalModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $httpClient = new TestHttpClient();

        $this->service = new Service();
        $this->service->addModule(
            new VpnPortalModule(
                new JsonTpl(),
                new ServerClient($httpClient, 'serverClient'),
                new CaClient($httpClient, 'caClient'),
                new TestSession()
            )
        );
        $this->service->addBeforeHook('auth', new NullAuthenticationHook('foo'));
    }

    public function testNewGet()
    {
        $this->assertSame(
            [
                'vpnPortalNew' => [
                    'poolList' => [
                        [
                            'poolId' => 'internet',
                            'displayName' => 'Internet Access',
                        ],
                    ],
                    'otpEnabledPools' => [],
                    'maxNameLength' => 60,
                ],
            ],
            $this->makeRequest('GET', '/new')
        );
    }

    public function testNewPost()
    {
        $this->assertSame(
            file_get_contents(sprintf('%s/Test/data/foo_MyConfig.ovpn', __DIR__)),
            $this->makeRequest(
                'POST',
                '/new',
                [],
                ['configName' => 'MyConfig', 'poolId' => 'internet'],
                true
            )->getBody()
        );
    }

    public function testAccount()
    {
        $this->assertSame(
            [
                'vpnPortalAccount' => [
                    'otpEnabledPools' => [],
                    'hasOtpSecret' => false,
                    'userId' => 'foo',
                    'userGroups' => [],
                ],
            ],
            $this->makeRequest('GET', '/account')
        );
    }

    public function testConfigurations()
    {
        $this->assertSame(
            [
                'vpnPortalConfigurations' => [
                    'userCertificateList' => [
                        [
                            'name' => 'FooConfig',
                            'user_id' => 'foo',
                            'state' => 'V',
                        ],
                    ],
                ],
            ],
            $this->makeRequest('GET', '/configurations')
        );
    }

    public function testDisable()
    {
        $this->assertSame(
            [
                'vpnPortalConfirmDisable' => [
                    'configName' => 'DeleteXYZ',
                ],
            ],
            $this->makeRequest('POST', '/disableCertificate', [], ['configName' => 'DeleteXYZ'])
        );
    }

    public function testDisableConfirm()
    {
        $this->assertSame(
            302,
            $this->makeRequest('POST', '/disableCertificateConfirm', [], ['configName' => 'DeleteXYZ', 'confirmDisable' => 'yes'], true)->getStatusCode()
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
                    'PATH_INFO' => $pathInfo,
                    'REQUEST_URI' => $pathInfo,
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
