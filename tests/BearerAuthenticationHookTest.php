<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use DateTime;
use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\ClientInfo;
use fkooman\OAuth\Server\SodiumSigner;
use fkooman\OAuth\Server\Storage;
use PDO;
use PHPUnit\Framework\TestCase;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Portal\BearerAuthenticationHook;

class BearerAuthenticationHookTest extends TestCase
{
    /** @var Storage */
    private $storage;

    /** @var string */
    private $keyPair;

    /** @var callable */
    private $getClientInfo;

    public function setUp()
    {
        $config = new Config(
            [
                'apiConsumers' => [
                    'token-client' => [
                        // just named here token-client because the signed
                        // access_token below was generated with token-client...
                        'redirect_uri_list' => ['http://example.org/code-cb'],
                        'display_name' => 'Code Client',
                    ],
                ],
            ]
        );

        $this->storage = new Storage(new PDO('sqlite::memory:'));
        $this->storage->init();
        $this->storage->storeAuthorization('foo', 'code-client', 'config', 'random_1');
        $this->keyPair = '2y5vJlGqpjTzwr3Ym3UqNwJuI1BKeLs53fc6Zf84kbYcP2/6Ar7zgiPS6BL4bvCaWN4uatYfuP7Dj/QvdctqJRw/b/oCvvOCI9LoEvhu8JpY3i5q1h+4/sOP9C91y2ol';
        $this->getClientInfo = function ($clientId) use ($config) {
            if (false === $config->getSection('apiConsumers')->hasItem($clientId)) {
                return false;
            }

            return new ClientInfo($config->getSection('apiConsumers')->getItem($clientId));
        };
    }

    public function testNoAuth()
    {
        $request = self::getRequest(
            [
            ]
        );

        $validator = new BearerValidator($this->storage, $this->getClientInfo, new SodiumSigner(base64_decode($this->keyPair, true)));
        $validator->setDateTime(new DateTime('2016-01-01'));
        $bearerAuthenticationHook = new BearerAuthenticationHook($validator, []);
        $tokenResponse = $bearerAuthenticationHook->executeBefore($request, []);
        $this->assertSame(401, $tokenResponse->getStatusCode());
        $this->assertSame(
            'Bearer realm="OAuth",error="invalid_token",error_description="invalid Bearer token"',
            $tokenResponse->getHeader('WWW-Authenticate')
        );
    }

    public function testValidToken()
    {
        $request = self::getRequest(
            [
                'HTTP_AUTHORIZATION' => 'Bearer qrCFqzPz4ac7U8/fSOa6ReXvDJ6D8zsz1VNK/yEHrryWHpHanbHjVgL6Ss+pLenWgTVTOHcLLv1aT3D1RTnmAnsidHlwZSI6ImFjY2Vzc190b2tlbiIsImF1dGhfa2V5IjoicmFuZG9tXzEiLCJ1c2VyX2lkIjoiZm9vIiwiY2xpZW50X2lkIjoidG9rZW4tY2xpZW50Iiwic2NvcGUiOiJjb25maWciLCJleHBpcmVzX2F0IjoiMjAxNi0wMS0wMSAwMTowMDowMCJ9',
            ]
        );

        $validator = new BearerValidator($this->storage, $this->getClientInfo, new SodiumSigner(base64_decode($this->keyPair, true)));
        $validator->setDateTime(new DateTime('2016-01-01'));
        $bearerAuthenticationHook = new BearerAuthenticationHook($validator, []);
        $this->assertSame('foo', $bearerAuthenticationHook->executeBefore($request, [])->id());
    }

    private static function getRequest(array $additionalHeaders = [])
    {
        $requestHeaders = [
            'SERVER_NAME' => 'vpn.example',
            'SERVER_PORT' => 80,
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SCRIPT_NAME' => '/index.php',
        ];

        return new Request(
            array_merge($requestHeaders, $additionalHeaders)
        );
    }
}
