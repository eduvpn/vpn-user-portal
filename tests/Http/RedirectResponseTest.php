<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\Http\RedirectResponse;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class RedirectResponseTest extends TestCase
{
    public function testRedirect(): void
    {
        $response = new RedirectResponse('http://vpn.example.org/foo');
        static::assertSame('http://vpn.example.org/foo', $response->getHeader('Location'));
        static::assertSame(302, $response->getStatusCode());
    }
}
