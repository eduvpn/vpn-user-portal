<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\Signify;
use PHPUnit\Framework\TestCase;

class SignifyTest extends TestCase
{
    public function testVerify()
    {
        $signify = new Signify(
            file_get_contents(__DIR__.'/data/signify/pub.key')
        );
        $this->assertTrue(
            $signify->verify(
                file_get_contents(__DIR__.'/data/signify/message.txt'),
                file_get_contents(__DIR__.'/data/signify/message.txt.minisig')
            )
        );
    }
}
