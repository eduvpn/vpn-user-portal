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
use Vpn\Portal\Cfg\RadiusAuthConfig;
use Vpn\Portal\Http\Auth\Exception\CredentialValidatorException;
use Vpn\Portal\Http\Auth\RadiusCredentialValidator;
use Vpn\Portal\NullLogger;

class RadiusCredentialValidatorTest extends TestCase
{
    public function testNullByte(): void
    {
        // radius extension is only available in Debian 11, the other platforms
        // no longer support it
        if (false === \extension_loaded('radius')) {
            $this->markTestSkipped();

            return;
        }

        $this->expectException(CredentialValidatorException::class);
        $this->expectExceptionMessage('"authUser" contains "\x00"');
        $r = new RadiusCredentialValidator(new NullLogger(), new RadiusAuthConfig([]));
        $r->validate("foo\x00bar", 'bar');
    }
}
