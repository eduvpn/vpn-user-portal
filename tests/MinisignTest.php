<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\Minisign;
use PHPUnit\Framework\TestCase;

class MinisignTest extends TestCase
{
    public function testVerify()
    {
        $this->assertTrue(
            Minisign::verify(
                file_get_contents(__DIR__.'/data/minisign/message.txt'),
                file_get_contents(__DIR__.'/data/minisign/message.txt.minisig'),
                [
                    file_get_contents(__DIR__.'/data/minisign/pub.key'),
                ]
            )
        );
    }
}
