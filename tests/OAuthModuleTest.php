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
use PHPUnit\Framework\TestCase;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\NullAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Portal\OAuthModule;

class OAuthModuleTest extends TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $config = new Config(
            [
                'apiConsumers' => [
                    'code-client' => [
                        'redirect_uri' => 'http://example.org/code-cb',
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

//    public function testAuthorizeCode()
//    {
//        $response = $this->makeRequest(
//            'GET',
//            '/_oauth/authorize',
//            [
//                'client_id' => 'code-client',
//                'redirect_uri' => 'http://example.org/code-cb',
//                'response_type' => 'code',
//                'scope' => 'config',
//                'state' => '12345',
//                'code_challenge_method' => 'S256',
//                'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
//            ]
//        );
//        $this->assertSame(200, $response->getStatusCode());
//        $this->assertSame(
//            [
//                'Content-Type' => 'text/html; charset=utf-8'
//            ],
//            $response->getHeaders()
//        );
//        $this->assertSame(
//            '',

    ////        $this->assertSame(
    ////            [
    ////                'authorizeOAuthClient' => [
    ////                    'client_id' => 'code-client',
    ////                    'display_name' => 'Code Client',
    ////                    'scope' => 'config',
    ////                    'redirect_uri' => 'http://example.org/code-cb',
    ////                ],
    ////            ],

//            $response->getBody()
//        );
//    }

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
            ]
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            [
                'Content-Type' => 'text/html; charset=utf-8',
                'Location' => 'http://example.org/code-cb?code=nmVljssjTwA29QjWrzieuAQjwR0yJo6DodWaTAa72t03WWyGDA8ajTdUy0Dzklrzx4kUjkL7MX%2FBaE2PUuykBHsidHlwZSI6ImF1dGhvcml6YXRpb25fY29kZSIsImF1dGhfa2V5IjoicmFuZG9tXzEiLCJ1c2VyX2lkIjoiZm9vIiwiY2xpZW50X2lkIjoiY29kZS1jbGllbnQiLCJzY29wZSI6ImNvbmZpZyIsInJlZGlyZWN0X3VyaSI6Imh0dHA6XC9cL2V4YW1wbGUub3JnXC9jb2RlLWNiIiwiY29kZV9jaGFsbGVuZ2UiOiJFOU1lbGhvYTJPd3ZGckVNVEpndUNIYW9lSzF0OFVSV2J1R0pTc3R3LWNNIiwiZXhwaXJlc19hdCI6IjIwMTYtMDEtMDEgMDA6MDU6MDAifQ%3D%3D&state=12345',
            ],
            $response->getHeaders()
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

    private function makeRequest($requestMethod, $pathInfo, array $getData = [], array $postData = [])
    {
        return $this->service->run(
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
    }
}
