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

use DateTime;
use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\TokenStorage;
use PDO;
use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Portal\Test\JsonTpl;

class OAuthTokenModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $random = $this->getMockBuilder('\fkooman\OAuth\Server\RandomInterface')->getMock();
        $random->method('get')->will($this->onConsecutiveCalls('random_1', 'random_2'));

        $config = new Config(
            [
                'apiConsumers' => [
                    'token-client' => [
                        'redirect_uri' => 'http://example.org/token-cb',
                        'response_type' => 'token',
                        'display_name' => 'Token Client',
                    ],
                    'code-client' => [
                        'redirect_uri' => 'http://example.org/code-cb',
                        'response_type' => 'code',
                        'display_name' => 'Code Client',
                    ],
                ],
            ]
        );

        $tokenStorage = new TokenStorage(new PDO('sqlite::memory:'));
        $tokenStorage->init();

        $tokenStorage->storeCode('foo', '12345', 'abcdefgh', 'code-client', 'config', 'http://example.org/code-cb', new DateTime('2016-01-01'), 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');

        $this->service = new Service();
        $this->service->addModule(
            new OAuthTokenModule(
                new OAuthServer(
                    $tokenStorage,
                    $random,
                    new DateTime('2016-01-01'),
                    function ($clientId) use ($config) {
                        if (false === $config->getSection('apiConsumers')->hasItem($clientId)) {
                            return false;
                        }

                        return $config->getSection('apiConsumers')->getItem($clientId);
                    }
                )
            )
        );
    }

    public function testPostToken()
    {
        $this->assertSame(
            [
                'access_token' => 'cmFuZG9tXzE.cmFuZG9tXzI',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ],
            $this->makeRequest(
                'POST',
                '/token',
                [],
                [
                    'grant_type' => 'authorization_code',
                    'code' => '12345.abcdefgh',
                    'redirect_uri' => 'http://example.org/code-cb',
                    'client_id' => 'code-client',
                    'code_verifier' => 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk',
                ],
                false
            )
        );
    }

    public function testExpiredCode()
    {
    }

    /**
     * Test getting access_token when one was already issued to same client
     * with same scope.
     */
    public function testAuthorizeTokenExistingAccessToken()
    {
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

        return json_decode($response->getBody(), true);
    }
}
