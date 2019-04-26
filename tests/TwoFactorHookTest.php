<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Common\Http\TwoFactorHook;
use LC\Common\Http\UserInfo;
use LC\Common\Tests\TestTpl;
use PHPUnit\Framework\TestCase;

class TwoFactorHookTest extends TestCase
{
    /**
     * @return void
     */
    public function testAlreadyVerified()
    {
        $session = new TestSession();
        $session->set('_two_factor_verified', 'foo');
        $tpl = new TestTpl();
        $formAuthentication = new TwoFactorHook($session, $tpl, false);
        $request = new TestRequest([]);
        $this->assertTrue($formAuthentication->executeBefore($request, ['auth' => new UserInfo('foo', [])]));
    }

    /**
     * @return void
     */
    public function testNotRequiredEnrolled()
    {
        $session = new TestSession();
        $tpl = new TestTpl();
        $formAuthentication = new TwoFactorHook($session, $tpl, false);
        $request = new TestRequest([]);
        $response = $formAuthentication->executeBefore($request, ['auth' => new UserInfo('foo', [])]);
        $this->assertSame('{"twoFactorTotp":{"_two_factor_user_id":"foo","_two_factor_auth_invalid":false,"_two_factor_auth_redirect_to":"http:\/\/vpn.example\/"}}', $response->getBody());
    }

    /**
     * @return void
     */
    public function testNotRequiredNotEnrolled()
    {
        $session = new TestSession();
        $tpl = new TestTpl();
        $formAuthentication = new TwoFactorHook($session, $tpl, false);
        $request = new TestRequest([]);
        $this->assertTrue($formAuthentication->executeBefore($request, ['auth' => new UserInfo('bar', [])]));
    }

    /**
     * @return void
     */
    public function testRequireTwoFactorNotEnrolled()
    {
        $session = new TestSession();
        $tpl = new TestTpl();
        $formAuthentication = new TwoFactorHook($session, $tpl, true);
        $request = new TestRequest([]);
        $response = $formAuthentication->executeBefore($request, ['auth' => new UserInfo('bar', [])]);
        $this->assertSame('http://vpn.example/', $session->get('_two_factor_enroll_redirect_to'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://vpn.example/two_factor_enroll', $response->getHeader('Location'));
    }

    /**
     * @expectedException \LC\Common\Http\Exception\HttpException
     *
     * @expectedExceptionMessage two-factor code not bound to authenticated user
     *
     * @return void
     */
    public function testNotBoundToAuth()
    {
        // if you have access to two accounts using e.g. MellonAuth you could
        // use the cookie from one OTP-authenticated account in the other
        // without needing the OTP secret! So basically reducing the
        // authentication to one factor for the (admin) portal. This binding
        // makes sure that the authenticated user MUST be the same as the
        // one used for the two_factor verification
        $session = new TestSession();
        $session->set('_two_factor_verified', 'bar');
        $tpl = new TestTpl();
        $formAuthentication = new TwoFactorHook($session, $tpl, false);
        $request = new TestRequest([]);
        $this->assertTrue($formAuthentication->executeBefore($request, ['auth' => new UserInfo('foo', [])]));
    }
}
