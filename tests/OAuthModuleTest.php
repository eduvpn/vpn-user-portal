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
use SURFnet\VPN\Common\Http\NullAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Portal\Test\JsonTpl;

class OAuthModuleTest extends PHPUnit_Framework_TestCase
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
            new OAuthModule(
                new JsonTpl(),
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
        $this->service->addBeforeHook('auth', new NullAuthenticationHook('foo'));
    }

    public function testAuthorizeToken()
    {
        $this->assertSame(
            [
                'authorizeOAuthClient' => [
                    'client_id' => 'token-client',
                    'display_name' => 'Token Client',
                    'scope' => 'config',
                    'redirect_uri' => 'http://example.org/token-cb',
                ],
            ],
            $this->makeRequest(
                'GET',
                '/_oauth/authorize',
                [
                    'client_id' => 'token-client',
                    'redirect_uri' => 'http://example.org/token-cb',
                    'response_type' => 'token',
                    'scope' => 'config',
                    'state' => '12345',
                ]
            ),
            true
        );
    }

    public function testAuthorizeCode()
    {
        $this->assertSame(
            [
                'authorizeOAuthClient' => [
                    'client_id' => 'code-client',
                    'display_name' => 'Code Client',
                    'scope' => 'config',
                    'redirect_uri' => 'http://example.org/code-cb',
                ],
            ],
            $this->makeRequest(
                'GET',
                '/_oauth/authorize',
                [
                    'client_id' => 'code-client',
                    'redirect_uri' => 'http://example.org/code-cb',
                    'response_type' => 'code',
                    'scope' => 'config',
                    'state' => '12345',
                    'code_challenge_method' => 'S256',
                    'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
                ]
            )
        );
    }

    public function testAuthorizeTokenPost()
    {
        $response = $this->makeRequest(
            'POST',
            '/_oauth/authorize',
            [
                'client_id' => 'token-client',
                'redirect_uri' => 'http://example.org/token-cb',
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
            'http://example.org/token-cb#access_token=cmFuZG9tXzE.cmFuZG9tXzI&state=12345',
            $response->getHeader('Location')
        );
    }

    public function testAuthorizeCodePost()
    {
        $response = $this->makeRequest(
            'POST',
            '/_oauth/authorize',
            [
                'client_id' => 'code-client',
                'redirect_uri' => 'http://example.org/code-cb',
                'response_type' => 'code',
                'scope' => 'config',
                'state' => '12345',
                'code_challenge_method' => 'S256',
                'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            ],
            [
                'approve' => 'yes',
            ],
            true    // return response
        );
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            'http://example.org/code-cb?code=cmFuZG9tXzE.cmFuZG9tXzI&state=12345',
            $response->getHeader('Location')
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
