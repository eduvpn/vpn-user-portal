<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Common\Tests;

use LC\Portal\Tpl;
use PHPUnit\Framework\TestCase;

class TplTest extends TestCase
{
    /**
     * @return void
     */
    public function testToHuman()
    {
        $this->assertSame('0 B', Tpl::toHuman(0));
        $this->assertSame('1023 B', Tpl::toHuman(1023));
        $this->assertSame('1.00 KiB', Tpl::toHuman(1024));
        $this->assertSame('1.50 KiB', Tpl::toHuman(1024 + 512));
        $this->assertSame('2.00 KiB', Tpl::toHuman(2048));
        $this->assertSame('1.00 GiB', Tpl::toHuman(1024 * 1024 * 1024));
        $this->assertSame('512.00 KiB', Tpl::toHuman(1024 * 512));
        $this->assertSame('1.15 GiB', Tpl::toHuman(1234567890));
    }
}
