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
use fkooman\OAuth\Server\ClientInfo;
use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\SodiumSigner;
use LetsConnect\Common\Config;
use LetsConnect\Common\Http\NullAuthenticationHook;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\HttpClient\ServerClient;
use LetsConnect\Portal\OAuthModule;
use LetsConnect\Portal\OAuthStorage;
use PDO;
use PHPUnit\Framework\TestCase;

class OAuthModuleTest extends TestCase
{
    /** @var \LetsConnect\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $schemaDir = \dirname(__DIR__).'/schema';
        $config = new Config(
            [
                'apiConsumers' => [
                    'code-client' => [
                        'redirect_uri_list' => ['http://example.org/code-cb'],
                        'display_name' => 'Code Client',
                    ],
                ],
            ]
        );

        $httpClient = new TestHttpClient();
        $serverClient = new ServerClient($httpClient, 'serverClient');
        $storage = new OAuthStorage(new PDO('sqlite::memory:'), $schemaDir, $serverClient);
        $storage->init();

        $oauthServer = new OAuthServer(
            $storage,
            function ($clientId) use ($config) {
                if (false === $config->getSection('apiConsumers')->hasItem($clientId)) {
                    return false;
                }

                return new ClientInfo($config->getSection('apiConsumers')->getItem($clientId));
            },
            new SodiumSigner(file_get_contents(sprintf('%s/data/server.key', __DIR__)))
        );
        $oauthServer->setDateTime(new DateTime('2016-01-01'));
        $oauthServer->setRandom(new TestOAuthServerRandom());
        $oauthServer->setRefreshTokenExpiry(new DateInterval('P1Y'));

        $this->service = new Service();
        $this->service->addModule(
            new OAuthModule(
                new JsonTpl(),
                $oauthServer
            )
        );
        $this->service->addBeforeHook('auth', new NullAuthenticationHook('foo'));
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
            ]
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            [
                'Content-Type' => 'text/html; charset=utf-8',
                'Location' => 'http://example.org/code-cb?code=3544_z8zoWj4E7UxrI3RGmkBDiA3DpAMB20AxcMzujNxKNDv0Bzt_8ZrAW6Dq71YEDHMpABJ5QGeruejGSwmDHsidHlwZSI6ImF1dGhvcml6YXRpb25fY29kZSIsImF1dGhfa2V5IjoicmFuZG9tXzEiLCJ1c2VyX2lkIjoiZm9vIiwiY2xpZW50X2lkIjoiY29kZS1jbGllbnQiLCJzY29wZSI6ImNvbmZpZyIsInJlZGlyZWN0X3VyaSI6Imh0dHA6XC9cL2V4YW1wbGUub3JnXC9jb2RlLWNiIiwiY29kZV9jaGFsbGVuZ2UiOiJFOU1lbGhvYTJPd3ZGckVNVEpndUNIYW9lSzF0OFVSV2J1R0pTc3R3LWNNIiwiZXhwaXJlc19hdCI6IjIwMTYtMDEtMDFUMDA6MDU6MDArMDA6MDAifQ&state=12345',
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
