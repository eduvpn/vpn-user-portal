<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\Http\CsrfProtectionHook;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\Http\Request;
use PHPUnit\Framework\TestCase;

class CsrfProtectionHookTest extends TestCase
{
    public function testMismatchOrigin(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('CSRF protection failed: unexpected HTTP_ORIGIN');
        $serviceHook = new CsrfProtectionHook();
        $request = new Request(
            [
                'REQUEST_METHOD' => 'POST',
                'SERVER_NAME' => 'vpn.example.org',
                'SERVER_PORT' => '80',
                'REQUEST_URI' => '/index.php/new',
                'SCRIPT_NAME' => '/index.php',
                'HTTP_ACCEPT' => 'text/html',
                'HTTP_ORIGIN' => 'http://fake.example.org',
            ],
            [],
            []
        );

        $serviceHook->executeBefore($request, []);
    }

    public function testMismatchReferrer(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('CSRF protection failed: unexpected HTTP_REFERER');
        $serviceHook = new CsrfProtectionHook();
        $request = new Request(
            [
                'REQUEST_METHOD' => 'POST',
                'SERVER_NAME' => 'vpn.example.org',
                'SERVER_PORT' => '80',
                'REQUEST_URI' => '/index.php/new',
                'SCRIPT_NAME' => '/index.php',
                'HTTP_ACCEPT' => 'text/html',
                'HTTP_REFERER' => 'http://fake.example.org/index.php/foo',
            ],
            [],
            []
        );

        $serviceHook->executeBefore($request, []);
    }
}
