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

use DateTime;
use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\TokenStorage;
use PDO;
use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Common\Http\Request;

class BearerAuthenticationHookTest extends PHPUnit_Framework_TestCase
{
    /** @var TokenStorage */
    private $tokenStorage;

    public function setUp()
    {
        $this->tokenStorage = new TokenStorage(new PDO('sqlite::memory:'));
        $this->tokenStorage->init();

        $this->tokenStorage->storeToken(
            'foo',
            '1234',
            'abcdefgh',
            'vpn-companion',
            'create_config',
            new DateTime('2016-01-01 01:00:00')
        );
    }

    public function testNoAuth()
    {
        $request = self::getRequest(
            [
            ]
        );
        $bearerAuthenticationHook = new BearerAuthenticationHook(new BearerValidator($this->tokenStorage, new DateTime('2016-01-01')));
        $tokenResponse = $bearerAuthenticationHook->executeBefore($request, []);
        $this->assertSame(401, $tokenResponse->getStatusCode());
        $this->assertSame(
            'Bearer realm="OAuth"',
            $tokenResponse->getHeader('WWW-Authenticate')
        );
    }

    public function testValidToken()
    {
        $request = self::getRequest(
            [
                'HTTP_AUTHORIZATION' => 'Bearer 1234.abcdefgh',
            ]
        );
        $bearerAuthenticationHook = new BearerAuthenticationHook(new BearerValidator($this->tokenStorage, new DateTime('2016-01-01')));
        $this->assertSame('foo', $bearerAuthenticationHook->executeBefore($request, []));
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
