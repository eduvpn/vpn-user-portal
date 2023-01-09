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
use Vpn\Portal\Cfg\ApiConfig;
use Vpn\Portal\ServerList;

/**
 * @internal
 *
 * @coversNothing
 */
final class ServerListTest extends TestCase
{
    public function testObtainPublicKey(): void
    {
        $apiConfig = new ApiConfig(
            [
                'guestAccessServerListUrl' => 'https://disco.eduvpn.org/v2/server_list.json',
            ]
        );

        $serverList = new ServerList(__DIR__.'/data', $apiConfig);
        static::assertSame(
            'k7.pub.ilBYDgKmhgSz_Rl7.xZyt5VgXKozenoK2OPRZ5UliOf-8k-9hxqk6UitwSH4',
            $serverList->extractPublicKey('ilBYDgKmhgSz_Rl7')
        );
        static::assertSame(
            'https://guest-vpn.tuxed.net/',
            $serverList->extractBaseUrl('ilBYDgKmhgSz_Rl7')
        );
    }

    public function testObtainPublicKeyNoSuchKey(): void
    {
        $apiConfig = new ApiConfig(
            [
                'guestAccessServerListUrl' => 'https://disco.eduvpn.org/v2/server_list.json',
            ]
        );
        $serverList = new ServerList(__DIR__.'/data', $apiConfig);
        static::assertNull($serverList->extractPublicKey('XYZ'));
        static::assertNull($serverList->extractBaseUrl('XYZ'));
    }
}
