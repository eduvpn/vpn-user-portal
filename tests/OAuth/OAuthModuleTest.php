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

namespace SURFnet\VPN\Portal\OAuth;

require_once sprintf('%s/Test/JsonTpl.php', __DIR__);
require_once sprintf('%s/Test/TestRandom.php', __DIR__);

use DateTime;
use PDO;
use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\NullAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Portal\OAuth\Test\JsonTpl;
use SURFnet\VPN\Portal\OAuth\Test\TestRandom;

class OAuthModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $config = new Config(
            [
                'apiConsumers' => [
                    'vpn-companion' => [
                        'redirect_uri' => 'vpn://import/callback',
                        'response_type' => 'token',
                        'display_name' => 'eduVPN for Android',
                    ],
                ],
            ]
        );

        $tokenStorage = new TokenStorage(new PDO('sqlite::memory:'));
        $tokenStorage->init();

        $this->service = new Service();
        $this->service->addModule(
            new OAuthModule(
                new JsonTpl(),
                new OAuthServer(
                    $tokenStorage,
                    new TestRandom(),
                    new DateTime(),
                    function ($clientId) use ($config) {
                        if (false === $config->getSection('apiConsumers')->hasItem($clientId)) {
                            return false;
                        }

                        return $config->getSection('apiConsumers')->getItem($clientId);
                    }
                )
            )
        );
        $this->service->addBeforeHook('auth', new NullAuthenticationHook('foo'));
    }

    public function testAuthorize()
    {
        $this->assertSame(
            [
                'authorizeOAuthClient' => [
                    'client_id' => 'vpn-companion',
                    'display_name' => 'eduVPN for Android',
                    'scope' => 'config',
                    'redirect_uri' => 'vpn://import/callback',
                ],
            ],
            $this->makeRequest(
                'GET',
                '/_oauth/authorize',
                [
                    'client_id' => 'vpn-companion',
                    'redirect_uri' => 'vpn://import/callback',
                    'response_type' => 'token',
                    'scope' => 'config',
                    'state' => '12345',
                ]
            )
        );
    }

    public function testAuthorizePost()
    {
        $response = $this->makeRequest(
            'POST',
            '/_oauth/authorize',
            [
                'client_id' => 'vpn-companion',
                'redirect_uri' => 'vpn://import/callback',
                'response_type' => 'token',
                'scope' => 'config',
                'state' => '12345',
            ],
            [
                'approve' => 'yes',
            ],
            true    // return response
        );
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            'vpn://import/callback#access_token=abcd1234abcd1234.wxyz1234efgh5678wxyz1234efgh5678&state=12345',
            $response->getHeader('Location')
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
