<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\Tests;

use LetsConnect\Portal\ForeignKeyListFetcher;
use PHPUnit\Framework\TestCase;

class ForeignKeyListFetcherTest extends TestCase
{
    public function testFetch()
    {
        $tmpDir = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(10)));
        mkdir($tmpDir);
        $foreignKeyListFetcher = new ForeignKeyListFetcher($tmpDir);
        $foreignKeyListFetcher->update(
            new TestForeignKeyHttpClient(),
            [
                'development' => [
                    'discovery_url' => 'https://static.eduvpn.nl/disco/secure_internet_dev.json',
                    'public_key' => 'zzls4TZTXHEyV3yxaxag1DZw3tSpIdBoaaOjUGH/Rwg=',
                ],
            ]
        );
        $this->assertSame(
            [
                'Oec0kX4b9L1YRziz_Slw4Cm3xvItvWK5gMrmgEU9Bvk' => [
                    'public_key' => 'YarxOioSoT1yRRhwkVI61fq-nCgzz75sZ39vVEFyoKo',
                    'base_uri' => 'https://labrat.eduvpn.nl/',
                    'source_name' => 'development',
                ],
                '8jO1a--VD_hbC7Iud2L5IbnRMtOEVbXuYtVNMtXz1S8' => [
                    'public_key' => 'AV5A8J9miDPamzVKBr8rsuMPApt_5r9cHY-36dQJYAs',
                    'base_uri' => 'https://fedora-vpn.tuxed.net/',
                    'source_name' => 'development',
                ],
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
        $tmpDir = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(10)));
        mkdir($tmpDir);
        $foreignKeyListFetcher = new ForeignKeyListFetcher($tmpDir);
        $foreignKeyListFetcher->update(
            new TestForeignKeyHttpClient(),
            [
                'development' => [
                    'discovery_url' => 'https://static.eduvpn.nl/disco/secure_internet_dev.wrong.json',
                    'public_key' => 'zzls4TZTXHEyV3yxaxag1DZw3tSpIdBoaaOjUGH/Rwg=',
                ],
            ]
        );
    }

    public function testUpdateSameSeq()
    {
    }

    public function testUpdateNewSeq()
    {
    }

    public function testUpdateRollbackSeq()
    {
    }
}
