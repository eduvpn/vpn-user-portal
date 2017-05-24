<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
