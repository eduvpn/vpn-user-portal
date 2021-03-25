<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\Http\CsrfProtectionHook;
use LC\Portal\Http\Exception\HttpException;
use PHPUnit\Framework\TestCase;

class CsrfProtectionHookTest extends TestCase
{
    public function testGoodPostReferrer(): void
    {
        $request = new TestRequest(
            [
                'HTTP_ACCEPT' => 'text/html',
                'REQUEST_METHOD' => 'POST',
                'HTTP_REFERER' => 'http://vpn.example/foo',
            ]
        );

        $referrerCheckHook = new CsrfProtectionHook();
        $this->assertTrue($referrerCheckHook->executeBefore($request, []));
    }

    public function testGoodPostOrigin(): void
    {
        $request = new TestRequest(
            [
                'HTTP_ACCEPT' => 'text/html',
                'REQUEST_METHOD' => 'POST',
                'HTTP_ORIGIN' => 'http://vpn.example',
            ]
        );

        $referrerCheckHook = new CsrfProtectionHook();
        $this->assertTrue($referrerCheckHook->executeBefore($request, []));
    }

    public function testGet(): void
    {
        $request = new TestRequest(
            [
                'HTTP_ACCEPT' => 'text/html',
            ]
        );

        $referrerCheckHook = new CsrfProtectionHook();
        $this->assertFalse($referrerCheckHook->executeBefore($request, []));
    }

    public function testCheckPostNoReferrer(): void
    {
        try {
            $request = new TestRequest(
                [
                    'REQUEST_METHOD' => 'POST',
                    'HTTP_ACCEPT' => 'text/html',
                ]
            );

            $referrerCheckHook = new CsrfProtectionHook();
            $referrerCheckHook->executeBefore($request, []);
            self::fail();
        } catch (HttpException $e) {
            self::assertSame('CSRF protection failed, no HTTP_ORIGIN or HTTP_REFERER', $e->getMessage());
        }
    }

    public function testCheckPostWrongReferrer(): void
    {
        try {
            $request = new TestRequest(
                [
                'REQUEST_METHOD' => 'POST',
                'HTTP_REFERER' => 'http://www.attacker.org/foo',
                'HTTP_ACCEPT' => 'text/html',
                ]
            );

            $referrerCheckHook = new CsrfProtectionHook();
            $referrerCheckHook->executeBefore($request, []);
            self::fail();
        } catch (HttpException $e) {
            self::assertSame('CSRF protection failed: unexpected HTTP_REFERER', $e->getMessage());
        }
    }

    public function testCheckPostWrongOrigin(): void
    {
        try {
            $request = new TestRequest(
                [
                'REQUEST_METHOD' => 'POST',
                'HTTP_ORIGIN' => 'http://www.attacker.org',
                'HTTP_ACCEPT' => 'text/html',
                ]
            );

            $referrerCheckHook = new CsrfProtectionHook();
            $referrerCheckHook->executeBefore($request, []);
            self::fail();
        } catch (HttpException $e) {
            self::assertSame('CSRF protection failed: unexpected HTTP_ORIGIN', $e->getMessage());
        }
    }

    public function testNonBrowser(): void
    {
        $request = new TestRequest(
            [
                'REQUEST_METHOD' => 'POST',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $referrerCheckHook = new CsrfProtectionHook();
        $referrerCheckHook->executeBefore($request, []);
        $this->assertTrue(true);
    }
}
