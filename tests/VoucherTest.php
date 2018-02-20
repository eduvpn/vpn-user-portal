<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use DateTime;
use PDO;
use PHPUnit\Framework\TestCase;
use SURFnet\VPN\Portal\Voucher;

class VoucherTest extends TestCase
{
    /** @var \SURFnet\VPN\Portal\Voucher */
    private $voucher;

    public function setUp()
    {
        $dateTime = new DateTime('2018-01-01 13:37:00');
        $db = new PDO('sqlite::memory:');
        $this->voucher = new Voucher($db, $dateTime);
        $this->voucher->init();
        $this->voucher->addVoucher('foo', '12345');
    }

    public function testUseVoucherValid()
    {
        $this->assertTrue($this->voucher->useVoucher('baz', '12345'));
    }

    public function testUseVoucherInvalid()
    {
        $this->assertFalse($this->voucher->useVoucher('baz', 'abcde'));
    }
}
