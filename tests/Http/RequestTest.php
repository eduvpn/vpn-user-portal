<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use RangeException;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\Http\Request;

/**
 * @internal
 *
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
        $r->requirePostParameter('xyz', function ($s): void {
            throw new RangeException();
        });
    }
}
