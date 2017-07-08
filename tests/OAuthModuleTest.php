<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use DateTime;
use fkooman\OAuth\Server\ClientInfo;
use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\Storage;
use PDO;
use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\NullAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Portal\OAuthModule;

class OAuthModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    public function setUp()
    {
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

        $storage = new Storage(new PDO('sqlite::memory:'));
        $storage->init();

        $oauthServer = new OAuthServer(
            $storage,
            function ($clientId) use ($config) {
                if (false === $config->getSection('apiConsumers')->hasItem($clientId)) {
                    return false;
                }

                return new ClientInfo($config->getSection('apiConsumers')->getItem($clientId));
            },
            '2y5vJlGqpjTzwr3Ym3UqNwJuI1BKeLs53fc6Zf84kbYcP2/6Ar7zgiPS6BL4bvCaWN4uatYfuP7Dj/QvdctqJRw/b/oCvvOCI9LoEvhu8JpY3i5q1h+4/sOP9C91y2ol'
        );
        $oauthServer->setDateTime(new DateTime('2016-01-01'));
        $oauthServer->setRandom(new TestOAuthServerRandom());

        $this->service = new Service();
        $this->service->addModule(
            new OAuthModule(
                new JsonTpl(),
                $oauthServer
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
            )
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
            'http://example.org/token-cb#access_token=qrCFqzPz4ac7U8%2FfSOa6ReXvDJ6D8zsz1VNK%2FyEHrryWHpHanbHjVgL6Ss%2BpLenWgTVTOHcLLv1aT3D1RTnmAnsidHlwZSI6ImFjY2Vzc190b2tlbiIsImF1dGhfa2V5IjoicmFuZG9tXzEiLCJ1c2VyX2lkIjoiZm9vIiwiY2xpZW50X2lkIjoidG9rZW4tY2xpZW50Iiwic2NvcGUiOiJjb25maWciLCJleHBpcmVzX2F0IjoiMjAxNi0wMS0wMSAwMTowMDowMCJ9&token_type=bearer&expires_in=3600&state=12345',
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
            'http://example.org/code-cb?code=nmVljssjTwA29QjWrzieuAQjwR0yJo6DodWaTAa72t03WWyGDA8ajTdUy0Dzklrzx4kUjkL7MX%2FBaE2PUuykBHsidHlwZSI6ImF1dGhvcml6YXRpb25fY29kZSIsImF1dGhfa2V5IjoicmFuZG9tXzEiLCJ1c2VyX2lkIjoiZm9vIiwiY2xpZW50X2lkIjoiY29kZS1jbGllbnQiLCJzY29wZSI6ImNvbmZpZyIsInJlZGlyZWN0X3VyaSI6Imh0dHA6XC9cL2V4YW1wbGUub3JnXC9jb2RlLWNiIiwiY29kZV9jaGFsbGVuZ2UiOiJFOU1lbGhvYTJPd3ZGckVNVEpndUNIYW9lSzF0OFVSV2J1R0pTc3R3LWNNIiwiZXhwaXJlc19hdCI6IjIwMTYtMDEtMDEgMDA6MDU6MDAifQ%3D%3D&state=12345',
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
