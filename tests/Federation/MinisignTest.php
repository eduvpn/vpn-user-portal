<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Federation;

use LC\Portal\Federation\Minisign;
use PHPUnit\Framework\TestCase;

class MinisignTest extends TestCase
{
    public function testVerify()
    {
        $this->assertTrue(
            Minisign::verify(
                file_get_contents(__DIR__.'/data/message.txt'),
                file_get_contents(__DIR__.'/data/message.txt.minisig'),
                [
                    'RWT7vH6qeacXeCJvqdpeFDXsl+PkU2V8ATje/ZODt35x/j0H0LFbBeJR',
                ]
            )
        );
    }
}
