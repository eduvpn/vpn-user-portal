<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\Http\FormAuthentication;
use LC\Portal\Http\Service;
use LC\Portal\Http\SimpleAuth;
use LC\Portal\Tests\TestTpl;
use PHPUnit\Framework\TestCase;

class FormAuthenticationTest extends TestCase
{
    public function testAuthenticated(): void
    {
        $session = new TestSession();
        $session->set('_form_auth_user', 'foo');
        $session->set('_form_auth_permission_list', serialize(['foo']));

        $tpl = new TestTpl();
        $formAuthentication = new FormAuthentication(
            new SimpleAuth(
                [
                    // foo:bar
                    'foo' => '$2y$10$F4lt5FzX.wfr2s3jsTy9XuxU2T7J5R0bTnMbu.9MDjphIupbG54l6',
                ]
            ),
            $session,
            $tpl
        );

        $request = new TestRequest([]);

        $this->assertSame('foo', $formAuthentication->executeBefore($request, [])->getUserId());
    }

    public function testNotAuthenticated(): void
    {
        $session = new TestSession();
        $tpl = new TestTpl();
        $formAuthentication = new FormAuthentication(
            new SimpleAuth(
                [
                    // foo:bar
                    'foo' => '$2y$10$F4lt5FzX.wfr2s3jsTy9XuxU2T7J5R0bTnMbu.9MDjphIupbG54l6',
                ]
            ),
            $session,
            $tpl
        );

        $request = new TestRequest(
            [
            ]
        );

        $response = $formAuthentication->executeBefore($request, []);
        $this->assertSame('{"formAuthentication":{"_form_auth_invalid_credentials":false,"_form_auth_redirect_to":"http:\/\/vpn.example\/","_show_logout_button":false}}', $response->getBody());
    }

    public function testVerifyCorrect(): void
    {
        $session = new TestSession();
        $tpl = new TestTpl();
        $service = new Service();
        $formAuthentication = new FormAuthentication(
            new SimpleAuth(
                [
                    // foo:bar
                    'foo' => '$2y$10$F4lt5FzX.wfr2s3jsTy9XuxU2T7J5R0bTnMbu.9MDjphIupbG54l6',
                ]
            ),
            $session,
            $tpl
        );
        $formAuthentication->init($service);

        $request = new TestRequest(
            [
                'HTTP_REFERER' => 'http://vpn.example/account',
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/_form/auth/verify',
            ],
            [],
            [
                'userName' => 'foo',
                'userPass' => 'bar',
                '_form_auth_redirect_to' => 'http://vpn.example/account',
            ]
        );

        $response = $service->run($request);
        $this->assertSame('foo', $session->get('_form_auth_user'));
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testVerifyIncorrect(): void
    {
        $session = new TestSession();
        $tpl = new TestTpl();

        $service = new Service();
        $formAuthentication = new FormAuthentication(
            new SimpleAuth(
                [
                    'foo' => 'bar',
                ]
            ),
            $session,
            $tpl
        );
        $formAuthentication->init($service);

        $request = new TestRequest(
            [
                'HTTP_REFERER' => 'http://vpn.example/account',
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/_form/auth/verify',
            ],
            [],
            [
                'userName' => 'foo',
                'userPass' => 'baz',
                '_form_auth_redirect_to' => 'http://vpn.example/account',
            ]
        );

        $response = $service->run($request);
        $this->assertNull($session->get('_form_auth_user'));

        $this->assertSame('{"formAuthentication":{"_form_auth_invalid_credentials":true,"_form_auth_invalid_credentials_user":"foo","_form_auth_redirect_to":"http:\/\/vpn.example\/account","_show_logout_button":false}}', $response->getBody());
    }
}
