<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Portal\ForeignKeyListFetcher;

class ForeignKeyListFetcherTest extends PHPUnit_Framework_TestCase
{
    public function testFetch()
    {
        $tmpFile = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(10)));
        $foreignKeyListFetcher = new ForeignKeyListFetcher($tmpFile);
        $foreignKeyListFetcher->update(new TestOAuthHttpClient(), 'https://example.org/federation.json', 'E5On0JTtyUVZmcWd+I/FXRm32nSq8R2ioyW7dcu/U88=');
        $this->assertSame(
            [
                'labrat.eduvpn.nl' => 'wGos0zPERxPYZHyJXQXz/OOSCWWej27PEScjScJzXQ8=',
                'vpn.tuxed.net' => 'cfkwpMo/btz/j/1YsQJRQ3izYPjn3wehfDSiSXeLFBs=',
            ],
            $foreignKeyListFetcher->extract()
        );
    }

    public function testFetchUpdate()
    {
        $tmpFile = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(10)));
        copy(sprintf('%s/data/federation.json', __DIR__), $tmpFile);
        $foreignKeyListFetcher = new ForeignKeyListFetcher($tmpFile);
        $foreignKeyListFetcher->update(new TestOAuthHttpClient(), 'https://example.org/federation.json', 'E5On0JTtyUVZmcWd+I/FXRm32nSq8R2ioyW7dcu/U88=');
        $this->assertSame(
            [
                'labrat.eduvpn.nl' => 'wGos0zPERxPYZHyJXQXz/OOSCWWej27PEScjScJzXQ8=',
                'vpn.tuxed.net' => 'cfkwpMo/btz/j/1YsQJRQ3izYPjn3wehfDSiSXeLFBs=',
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
        $foreignKeyListFetcher->update(new TestOAuthHttpClient(), 'https://example.org/federation.json.wrong', 'E5On0JTtyUVZmcWd+I/FXRm32nSq8R2ioyW7dcu/U88=');
    }

    public function testFetchReplay()
    {
    }
}
