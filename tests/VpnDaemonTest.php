<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\NullLogger;
use Vpn\Portal\VpnDaemon;

/**
 * @internal
 *
 * @coversNothing
 */
final class VpnDaemonTest extends TestCase
{
    private TestHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new TestHttpClient();
    }

    public function testNodeInfo(): void
    {
        $vpnDaemon = new VpnDaemon(
            $this->httpClient,
            new NullLogger()
        );
        static::assertSame(
            [
                'rel_load_average' => [
                    24,
                    25,
                    31,
                ],
                'load_average' => [
                    0.48,
                    0.5,
                    0.63,
                ],
                'cpu_count' => 2,
            ],
            $vpnDaemon->nodeInfo('http://localhost:41194')
        );
    }
}
