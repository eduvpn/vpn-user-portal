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
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\Http\GuestApiService;

class GuestApiServiceTest extends TestCase
{
    public function testValidateGuestUserIdInvalidEncoding(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('[Guest]: User ID has invalid encoding');
        GuestApiService::validateGuestUserId('+');
    }

    public function testValidateGuestUserIdInvalidLength(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('[Guest]: User ID has invalid length');
        GuestApiService::validateGuestUserId('foo');
    }

    public function testValidateGuestUserIdGood(): void
    {
        // this should not throw an exception
        GuestApiService::validateGuestUserId('--SwaqFjx0FaibD4gJUQ8W4XGwyZ5pDaZnNeLXt88ZQ');
    }
}
