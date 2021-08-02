<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\Http\Service;
use LC\Portal\LogoutModule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class LogoutModuleTest extends TestCase
{
    public function testVerifyLogout(): void
    {
        $session = new TestSession();
        $service = new Service();
        $logoutModule = new LogoutModule($session, null, 'ReturnTo');
        $service->addModule($logoutModule);
        $request = new TestRequest(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/_logout',
                'HTTP_REFERER' => 'http://example.org/foo',
            ],
            [],
            []
        );
        $response = $service->run($request);
        static::assertSame(302, $response->getStatusCode());
        static::assertSame('http://example.org/foo', $response->getHeader('Location'));
    }

    public function testVerifyMellonLogoutWithUrl(): void
    {
        $session = new TestSession();
        $service = new Service();
        $logoutModule = new LogoutModule($session, 'http://vpn.example/saml/logout', 'ReturnTo');
        $service->addModule($logoutModule);
        $request = new TestRequest(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/_logout',
                'HTTP_REFERER' => 'http://example.org/foo',
            ],
            [],
            []
        );
        $response = $service->run($request);
        static::assertSame(302, $response->getStatusCode());
        static::assertSame('http://vpn.example/saml/logout?ReturnTo=http%3A%2F%2Fexample.org%2Ffoo', $response->getHeader('Location'));
    }
}
