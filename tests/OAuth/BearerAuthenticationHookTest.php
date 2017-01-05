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

        $this->tokenStorage->store(
            'foo',
            '1234',
            'abcdefgh',
            'vpn-companion',
            'create_config'
        );
    }

    /**
     * @expectedException \SURFnet\VPN\Common\Http\Exception\HttpException
     * @expectedExceptionMessage: no_token
     */
    public function testNoAuth()
    {
        $request = self::getRequest(
            [
            ]
        );
        $bearerAuthenticationHook = new BearerAuthenticationHook($this->tokenStorage);
        $bearerAuthenticationHook->executeBefore($request, []);
    }

    public function testValidToken()
    {
        $request = self::getRequest(
            [
                'HTTP_AUTHORIZATION' => 'Bearer 1234.abcdefgh',
            ]
        );
        $bearerAuthenticationHook = new BearerAuthenticationHook($this->tokenStorage);
        $bearerAuthenticationHook->executeBefore($request, []);
    }

    /**
     * @expectedException \SURFnet\VPN\Common\Http\Exception\HttpException
     * @expectedExceptionMessage: invalid_token
     */
    public function testInvalidAccessTokenKey()
    {
        $request = self::getRequest(
            [
                'HTTP_AUTHORIZATION' => 'Bearer aaaa.abcdefgh',
            ]
        );
        $bearerAuthenticationHook = new BearerAuthenticationHook($this->tokenStorage);
        $bearerAuthenticationHook->executeBefore($request, []);
    }

    /**
     * @expectedException \SURFnet\VPN\Common\Http\Exception\HttpException
     * @expectedExceptionMessage: invalid_token
     */
    public function testInvalidAccessToken()
    {
        $request = self::getRequest(
            [
                'HTTP_AUTHORIZATION' => 'Bearer 1234.aaaaaaaa',
            ]
        );
        $bearerAuthenticationHook = new BearerAuthenticationHook($this->tokenStorage);
        $bearerAuthenticationHook->executeBefore($request, []);
    }

    /**
     * @expectedException \SURFnet\VPN\Common\Http\Exception\HttpException
     * @expectedExceptionMessage: invalid_token
     */
    public function testInvalidSyntax()
    {
        $request = self::getRequest(
            [
                'HTTP_AUTHORIZATION' => 'Bearer %%%%',
            ]
        );
        $bearerAuthenticationHook = new BearerAuthenticationHook($this->tokenStorage);
        $bearerAuthenticationHook->executeBefore($request, []);
    }

    /**
     * @expectedException \SURFnet\VPN\Common\Http\Exception\HttpException
     * @expectedExceptionMessage: invalid_token
     */
    public function testNoDot()
    {
        $request = self::getRequest(
            [
                'HTTP_AUTHORIZATION' => 'Bearer abcdef',
            ]
        );
        $bearerAuthenticationHook = new BearerAuthenticationHook($this->tokenStorage);
        $bearerAuthenticationHook->executeBefore($request, []);
    }

    /**
     * @expectedException \SURFnet\VPN\Common\Http\Exception\HttpException
     * @expectedExceptionMessage: invalid_token
     */
    public function testBasicAuthentication()
    {
        $request = self::getRequest(
            [
                'HTTP_AUTHORIZATION' => 'Basic AAA===',
            ]
        );
        $bearerAuthenticationHook = new BearerAuthenticationHook($this->tokenStorage);
        $bearerAuthenticationHook->executeBefore($request, []);
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
