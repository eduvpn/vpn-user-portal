<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\Http\BearerAuthenticationHook;
use LC\Portal\Http\Exception\HttpException;
use PHPUnit\Framework\TestCase;

class BearerAuthenticationHookTest extends TestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testBearerAuthentication(): void
    {
        $bearerAuthentication = new BearerAuthenticationHook('foo');
        $request = new TestRequest(['HTTP_AUTHORIZATION' => 'Bearer foo']);
        $bearerAuthentication->executeBefore($request, []);
    }

    public function testBearerAuthenticationWrongToken(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('invalid token');
        $bearerAuthentication = new BearerAuthenticationHook('foo');
        $request = new TestRequest(['HTTP_AUTHORIZATION' => 'Bearer bar']);
        $bearerAuthentication->executeBefore($request, []);
    }

    public function testBearerAuthenticationNoToken(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('no token');
        $bearerAuthentication = new BearerAuthenticationHook('foo');
        $request = new TestRequest([]);
        $bearerAuthentication->executeBefore($request, []);
    }
}
