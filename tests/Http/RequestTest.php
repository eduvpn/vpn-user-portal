<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use RangeException;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\Http\Request;

/**
 * @internal
 * @coversNothing
 */
final class RequestTest extends TestCase
{
    public function testValidate(): void
    {
        $r = new Request(
            [],
            [],
            [
                'xyz' => 'foo',
            ],
            []
        );

        static::expectException(HttpException::class);
        static::expectExceptionMessage('invalid value for "xyz"');
        $r->requirePostParameter('xyz', function ($s): void { throw new RangeException(); });
    }

    public function testNotProxiedRequest(): void
    {
        # Purposely testing using HTTP on port 80 here, to make it possible to
        # build on top of this proof in the proxied request test, where HTTPS on
        # port 443 must override these properties.
        $r = new Request(
            [
                'SERVER_NAME' => 'the.server.name',
                'SERVER_PORT' => '80',
                'HTTPS' => 'off'
            ],
            [],
            [],
            []
        );
        static::assertSame('http', $r->getScheme());
        static::assertSame('the.server.name', $r->getServerName());
        static::assertSame(80, $r->getServerPort());
    }

    public function testProxiedRequest(): void
    {
        $r = new Request(
            [
                'SERVER_NAME' => 'internal.server.name',
                'SERVER_PORT' => '80',
                'HTTPS' => 'off',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_HOST' => 'external.server.name',
                'HTTP_X_FORWARDED_PORT' => '443',
            ],
            [],
            [],
            []
        );
        static::assertSame('https', $r->getScheme());
        static::assertSame('external.server.name', $r->getServerName());
        static::assertSame(443, $r->getServerPort());
    }
}
