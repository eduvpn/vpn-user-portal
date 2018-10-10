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
                    'code-client' => [
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

        $validator = new BearerValidator($this->storage, $this->getClientInfo, new SodiumSigner(file_get_contents(sprintf('%s/data/server.key', __DIR__))));
        $validator->setDateTime(new DateTime('2016-01-01'));
        $bearerAuthenticationHook = new BearerAuthenticationHook($validator);
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
                'HTTP_AUTHORIZATION' => 'Bearer dXvNt6RuSqxvwE-KU60ddP7dZ8rkj0faYzckfp3xLYIGtfgTwsMGl1XQRYmZ4pXBhSVanOsCgvXWM6eDOUQ4C3sidHlwZSI6ImFjY2Vzc190b2tlbiIsImF1dGhfa2V5IjoicmFuZG9tXzEiLCJ1c2VyX2lkIjoiZm9vIiwiY2xpZW50X2lkIjoiY29kZS1jbGllbnQiLCJzY29wZSI6ImNvbmZpZyIsImV4cGlyZXNfYXQiOiIyMDE2LTAxLTAxVDAxOjAwOjAwKzAwOjAwIn0',
            ]
        );

        $validator = new BearerValidator($this->storage, $this->getClientInfo, new SodiumSigner(file_get_contents(sprintf('%s/data/server.key', __DIR__))));

        $validator->setDateTime(new DateTime('2016-01-01'));
        $bearerAuthenticationHook = new BearerAuthenticationHook($validator);
        $this->assertSame('foo', $bearerAuthenticationHook->executeBefore($request, [])->getUserId());
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
