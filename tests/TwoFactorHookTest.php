<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\Http\Exception\HttpException;
use LC\Portal\Http\TwoFactorHook;
use LC\Portal\Http\UserInfo;
use LC\Portal\HttpClient\ServerClient;
use LC\Portal\Tests\TestTpl;
use PHPUnit\Framework\TestCase;

class TwoFactorHookTest extends TestCase
{
    public function testAlreadyVerified(): void
    {
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');
        $session = new TestSession();
        $session->set('_two_factor_verified', 'foo');
        $tpl = new TestTpl();
        $formAuthentication = new TwoFactorHook($session, $tpl, $serverClient, false);
        $request = new TestRequest([]);
        $this->assertTrue($formAuthentication->executeBefore($request, ['auth' => new UserInfo('foo', [])]));
    }

    public function testNotRequiredEnrolled(): void
    {
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');
        $session = new TestSession();
        $tpl = new TestTpl();
        $formAuthentication = new TwoFactorHook($session, $tpl, $serverClient, false);
        $request = new TestRequest([]);
        $response = $formAuthentication->executeBefore($request, ['auth' => new UserInfo('foo', [])]);
        $this->assertSame('{"twoFactorTotp":{"_two_factor_user_id":"foo","_two_factor_auth_invalid":false,"_two_factor_auth_redirect_to":"http:\/\/vpn.example\/"}}', $response->getBody());
    }

    public function testNotRequiredNotEnrolled(): void
    {
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');
        $session = new TestSession();
        $tpl = new TestTpl();
        $formAuthentication = new TwoFactorHook($session, $tpl, $serverClient, false);
        $request = new TestRequest([]);
        $this->assertTrue($formAuthentication->executeBefore($request, ['auth' => new UserInfo('bar', [])]));
    }

    public function testRequireTwoFactorNotEnrolled(): void
    {
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');
        $session = new TestSession();
        $tpl = new TestTpl();
        $formAuthentication = new TwoFactorHook($session, $tpl, $serverClient, true);
        $request = new TestRequest([]);
        $response = $formAuthentication->executeBefore($request, ['auth' => new UserInfo('bar', [])]);
        $this->assertSame('http://vpn.example/', $session->get('_two_factor_enroll_redirect_to'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://vpn.example/two_factor_enroll', $response->getHeader('Location'));
    }

    public function testNotBoundToAuth(): void
    {
        try {
            // if you have access to two accounts using e.g. MellonAuth you could
            // use the cookie from one OTP-authenticated account in the other
            // without needing the OTP secret! So basically reducing the
            // authentication to one factor for the (admin) portal. This binding
            // makes sure that the authenticated user MUST be the same as the
            // one used for the two_factor verification
            $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');
            $session = new TestSession();
            $session->set('_two_factor_verified', 'bar');
            $tpl = new TestTpl();
            $formAuthentication = new TwoFactorHook($session, $tpl, $serverClient, false);
            $request = new TestRequest([]);
            $this->assertTrue($formAuthentication->executeBefore($request, ['auth' => new UserInfo('foo', [])]));
            self::fail();
        } catch (HttpException $e) {
            self::assertSame('two-factor code not bound to authenticated user', $e->getMessage());
        }
    }
}
