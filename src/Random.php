<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use ParagonIE\ConstantTime\Hex;

class Random implements RandomInterface
{
    public function get(int $length): string
    {
        return Hex::encode(
            random_bytes($length)
        );
    }
}
