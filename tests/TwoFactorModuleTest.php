<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Common\Tests\Http;

use LC\Common\Http\NullAuthenticationHook;
use LC\Common\Http\Service;
use LC\Common\Http\TwoFactorModule;
use LC\Common\HttpClient\ServerClient;
use LC\Common\Tests\TestTpl;
use PHPUnit\Framework\TestCase;

class TwoFactorModuleTest extends TestCase
{
    /**
     * @return void
     */
    public function testVerifyCorrect()
    {
        $session = new TestSession();
        $tpl = new TestTpl();
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');

        $service = new Service();
        $twoFactorModule = new TwoFactorModule(
            $serverClient,
            $session,
            $tpl
        );
        $service->addBeforeHook('auth', new NullAuthenticationHook('foo'));

        $service->addModule($twoFactorModule);

        $request = new TestRequest(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/_two_factor/auth/verify/totp',
            ],
            [],
            [
                '_two_factor_auth_totp_key' => '123456',
                '_two_factor_auth_redirect_to' => 'http://vpn.example/account',
            ]
        );

        $response = $service->run($request);
        $this->assertSame('foo', $session->get('_two_factor_verified'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://vpn.example/account', $response->getHeader('Location'));
    }

    /**
     * @return void
     */
    public function testVerifyIncorrect()
    {
        $session = new TestSession();
        $tpl = new TestTpl();
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');

        $service = new Service();
        $twoFactorModule = new TwoFactorModule(
            $serverClient,
            $session,
            $tpl
        );
        $service->addBeforeHook('auth', new NullAuthenticationHook('bar'));
        $service->addModule($twoFactorModule);
        $request = new TestRequest(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/_two_factor/auth/verify/totp',
            ],
            [],
            [
                '_two_factor_auth_totp_key' => '123456',
                '_two_factor_auth_redirect_to' => 'http://vpn.example/account',
            ]
        );

        $response = $service->run($request);
        $this->assertNull($session->get('_two_factor_verified'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"twoFactorTotp":{"_two_factor_user_id":"bar","_two_factor_auth_invalid":true,"_two_factor_auth_error_msg":"invalid OTP key","_two_factor_auth_redirect_to":"http:\/\/vpn.example\/account"}}', $response->getBody());
    }
}
