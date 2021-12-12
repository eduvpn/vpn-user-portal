<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests\Http;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\Http\Request;

/**
 * @internal
 * @coversNothing
 */
final class RequestTest extends TestCase
{
    public function testGetServerName(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('vpn.example', $request->getServerName());
    }

    public function testGetRequestMethod(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('GET', $request->getRequestMethod());
    }

    public function testGetPathInfo(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/foo/bar',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('/foo/bar', $request->getPathInfo());
    }

    public function testMissingPathInfo(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('/', $request->getPathInfo());
    }

    public function testNoPathInfo(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/index.php',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('/', $request->getPathInfo());
    }

    public function testRequireQueryParameter(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/?user_id=foo',
                'SCRIPT_NAME' => '/index.php',
            ],
            [
                'user_id' => 'foo',
            ]
        );
        static::assertSame('foo', $request->requireQueryParameter('user_id'));
    }

    public function testOptionalQueryParameter(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/?user_id=foo',
                'SCRIPT_NAME' => '/index.php',
            ],
            [
                'user_id' => 'foo',
            ]
        );
        static::assertNull($request->optionalQueryParameter('foo'));
    }

    public function testGetMissingQueryParameter(): void
    {
        try {
            $request = new Request(
                [
                    'SERVER_NAME' => 'vpn.example',
                    'SERVER_PORT' => '80',
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/',
                    'SCRIPT_NAME' => '/index.php',
                ]
            );
            $request->requireQueryParameter('user_id');
            static::fail();
        } catch (HttpException $e) {
            static::assertSame('missing query parameter "user_id"', $e->getMessage());
        }
    }

    public function testGetPostParameter(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SCRIPT_NAME' => '/index.php',
            ],
            [],
            [
                'user_id' => 'foo',
            ]
        );
        static::assertSame('foo', $request->requirePostParameter('user_id'));
    }

    public function testRequireHeader(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_ACCEPT' => 'text/html',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('text/html', $request->requireHeader('HTTP_ACCEPT'));
    }

    public function testOptionalHeader(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_ACCEPT' => 'text/html',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('text/html', $request->optionalHeader('HTTP_ACCEPT'));
        static::assertNull($request->optionalHeader('HTTP_FOO'));
    }

    public function testRequestUri(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('http://vpn.example/', $request->getUri());
    }

    public function testHttpsRequestUri(): void
    {
        $request = new Request(
            [
                'REQUEST_SCHEME' => 'https',
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '443',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('https://vpn.example/', $request->getUri());
    }

    public function testNonStandardPortRequestUri(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '8080',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('http://vpn.example:8080/', $request->getUri());
    }

    public function testGetRootSimple(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('/', $request->getRoot());
    }

    public function testGetRootSame(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/connection',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('/', $request->getRoot());
    }

    public function testGetRootPathInfo(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/admin/foo/bar',
                'SCRIPT_NAME' => '/admin/index.php',
            ]
        );
        static::assertSame('/admin/', $request->getRoot());
    }

    public function testScriptNameInRequestUri(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/admin/index.php/foo/bar',
                'SCRIPT_NAME' => '/admin/index.php',
            ]
        );
        static::assertSame('/admin/', $request->getRoot());
        static::assertSame('/foo/bar', $request->getPathInfo());
    }

    public function testGetRootQueryString(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/?foo=bar',
                'SCRIPT_NAME' => '/index.php',
            ]
        );
        static::assertSame('/', $request->getRoot());
        static::assertSame('/', $request->getPathInfo());
    }

    public function testGetRootPathInfoQueryString(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/admin/foo/bar?foo=bar',
                'SCRIPT_NAME' => '/admin/index.php',
            ]
        );
        static::assertSame('/admin/', $request->getRoot());
        static::assertSame('/foo/bar', $request->getPathInfo());
    }
}
