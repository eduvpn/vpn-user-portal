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
use Vpn\Portal\Http\Auth\AbstractAuthModule;

class AbstractAuthModuleTest extends TestCase
{
    public function testEmptyFlatten(): void
    {
        $this->assertSame(
            [],
            AbstractAuthModule::flattenPermissionList(
                [
                ]
            )
        );
    }

    public function testSimpleFlatten(): void
    {
        $this->assertSame(
            [
                'A!eduPersonEntitlement!foo',
                'A!eduPersonEntitlement!bar',
            ],
            AbstractAuthModule::flattenPermissionList(
                [
                    'eduPersonEntitlement' => [
                        'foo',
                        'bar',
                    ],
                ]
            )
        );
    }

    public function testMultiFlatten(): void
    {
        $this->assertSame(
            [
                'A!eduPersonEntitlement!foo',
                'A!eduPersonEntitlement!bar',
                'A!eduPersonAffiliation!student',
            ],
            AbstractAuthModule::flattenPermissionList(
                [
                    'eduPersonEntitlement' => [
                        'foo',
                        'bar',
                    ],
                    'eduPersonAffiliation' => [
                        'student',
                    ],
                    [],
                ]
            )
        );
    }

    public function testMultiFlattenFilter(): void
    {
        $this->assertSame(
            [
                'A!eduPersonEntitlement!foo',
                'A!eduPersonEntitlement!bar',
            ],
            AbstractAuthModule::flattenPermissionList(
                [
                    'eduPersonEntitlement' => [
                        'foo',
                        'bar',
                    ],
                    'eduPersonAffiliation' => [
                        'student',
                    ],
                    [],
                ],
                [
                    'eduPersonEntitlement',
                ]
            )
        );
    }
}
