<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use PHPUnit\Framework\TestCase;
use SURFnet\VPN\Portal\ForeignKeyListFetcher;

class ForeignKeyListFetcherTest extends TestCase
{
    public function testFetch()
    {
        $tmpFile = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(10)));
        $foreignKeyListFetcher = new ForeignKeyListFetcher($tmpFile);
        $foreignKeyListFetcher->update(new TestForeignKeyHttpClient(), 'https://example.org/federation.json', 'E5On0JTtyUVZmcWd+I/FXRm32nSq8R2ioyW7dcu/U88=');
        $this->assertSame(
            [
                'labrat.eduvpn.nl' => base64_decode('wGos0zPERxPYZHyJXQXz/OOSCWWej27PEScjScJzXQ8=', true),
                'vpn.tuxed.net' => base64_decode('cfkwpMo/btz/j/1YsQJRQ3izYPjn3wehfDSiSXeLFBs=', true),
            ],
            $foreignKeyListFetcher->extract()
        );
    }

    public function testFetchUpdate()
    {
        $tmpFile = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(10)));
        copy(sprintf('%s/data/federation.json', __DIR__), $tmpFile);
        $foreignKeyListFetcher = new ForeignKeyListFetcher($tmpFile);
        $foreignKeyListFetcher->update(new TestForeignKeyHttpClient(), 'https://example.org/federation.json', 'E5On0JTtyUVZmcWd+I/FXRm32nSq8R2ioyW7dcu/U88=');
        $this->assertSame(
            [
                'labrat.eduvpn.nl' => base64_decode('wGos0zPERxPYZHyJXQXz/OOSCWWej27PEScjScJzXQ8=', true),
                'vpn.tuxed.net' => base64_decode('cfkwpMo/btz/j/1YsQJRQ3izYPjn3wehfDSiSXeLFBs=', true),
            ],
            $foreignKeyListFetcher->extract()
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage unable to verify signature
     */
    public function testFetchWrongSignature()
    {
        $tmpFile = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(10)));
        $foreignKeyListFetcher = new ForeignKeyListFetcher($tmpFile);
        $foreignKeyListFetcher->update(new TestForeignKeyHttpClient(), 'https://example.org/federation.json.wrong', 'E5On0JTtyUVZmcWd+I/FXRm32nSq8R2ioyW7dcu/U88=');
    }

    public function testFetchReplay()
    {
    }
}
