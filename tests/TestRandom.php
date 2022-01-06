<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use Vpn\Portal\RandomInterface;

class TestRandom implements RandomInterface
{
    public function get(int $len): string
    {
        return str_repeat("\x00", $len);
    }
}
