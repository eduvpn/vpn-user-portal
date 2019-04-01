<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\ForeignKeyListFetcher;
use PHPUnit\Framework\TestCase;

class ForeignKeyListFetcherTest extends TestCase
{
    /**
     * @return void
     */
    public function testFetch()
    {
        $tmpDir = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(10)));
        mkdir($tmpDir);
        $foreignKeyListFetcher = new ForeignKeyListFetcher($tmpDir);
        $foreignKeyListFetcher->update(
            new TestForeignKeyHttpClient(),
            [
                'development_v2' => [
                    'discovery_url' => 'https://static.eduvpn.nl/disco/secure_internet_dev_v2.json',
                    'public_key' => 's8YDiDF/6zN5cvdeHLaptla/ZWgr7MRCnrANQNKGWBE=',
                ],
            ]
        );
        $this->assertSame(
            [
                'r-iSpOHTPKX_KCVr0P-J9OT-k9BcELdGYrpx4g7q0MM' => [
                    'public_key' => 'bSWjOYkCI9kqRqTMRjAKPliwRMVt64BfirD_b35WBmc',
                    'base_uri' => 'https://vpn01.tuxed.net/',
                    'source_name' => 'development_v2',
                ],
                'Qtbplvcpf7aVGcDTpDuHZR6CQ3QtiZxrVI9fQHg0p60' => [
                    'public_key' => 'H_dwogrDWgHucvIao7fXorO6RlNz8xi4tGCDzZGX-Sk',
                    'base_uri' => 'https://vpn02.tuxed.net/',
                    'source_name' => 'development_v2',
                ],
            ],
            $foreignKeyListFetcher->extract()
        );
    }

    /**
     * @expectedException \RuntimeException
     *
     * @expectedExceptionMessage unable to verify signature
     *
     * @return void
     */
    public function testFetchWrongSignature()
    {
        $tmpDir = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(10)));
        mkdir($tmpDir);
        $foreignKeyListFetcher = new ForeignKeyListFetcher($tmpDir);
        $foreignKeyListFetcher->update(
            new TestForeignKeyHttpClient(),
            [
                'development_v2' => [
                    'discovery_url' => 'https://static.eduvpn.nl/disco/secure_internet_dev_v2.wrong.json',
                    'public_key' => 's8YDiDF/6zN5cvdeHLaptla/ZWgr7MRCnrANQNKGWBE=',
                ],
            ]
        );
    }

    /**
     * @return void
     */
    public function testUpdateSameSeq()
    {
    }

    /**
     * @return void
     */
    public function testUpdateNewSeq()
    {
    }

    /**
     * @return void
     */
    public function testUpdateRollbackSeq()
    {
    }
}
