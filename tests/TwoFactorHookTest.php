<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Common\Tests\Http;

use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\TwoFactorHook;
use LC\Common\Http\UserInfo;
use LC\Common\HttpClient\ServerClient;
use LC\Common\Tests\TestTpl;
use PHPUnit\Framework\TestCase;

class TwoFactorHookTest extends TestCase
{
    /**
     * @return void
     */
    public function testAlreadyVerified()
    {
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');
        $session = new TestSession();
        $session->set('_two_factor_verified', 'foo');
        $tpl = new TestTpl();
        $formAuthentication = new TwoFactorHook($session, $tpl, $serverClient, false);
        $request = new TestRequest([]);
        $this->assertTrue($formAuthentication->executeBefore($request, ['auth' => new UserInfo('foo', [])]));
    }

    /**
     * @return void
     */
    public function testNotRequiredEnrolled()
    {
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');
        $session = new TestSession();
        $tpl = new TestTpl();
        $formAuthentication = new TwoFactorHook($session, $tpl, $serverClient, false);
        $request = new TestRequest([]);
        $response = $formAuthentication->executeBefore($request, ['auth' => new UserInfo('foo', [])]);
        $this->assertSame('{"twoFactorTotp":{"_two_factor_user_id":"foo","_two_factor_auth_invalid":false,"_two_factor_auth_redirect_to":"http:\/\/vpn.example\/"}}', $response->getBody());
    }

    /**
     * @return void
     */
    public function testNotRequiredNotEnrolled()
    {
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');
        $session = new TestSession();
        $tpl = new TestTpl();
        $formAuthentication = new TwoFactorHook($session, $tpl, $serverClient, false);
        $request = new TestRequest([]);
        $this->assertTrue($formAuthentication->executeBefore($request, ['auth' => new UserInfo('bar', [])]));
    }

    /**
     * @return void
     */
    public function testRequireTwoFactorNotEnrolled()
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

    /**
     * @return void
     */
    public function testNotBoundToAuth()
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
