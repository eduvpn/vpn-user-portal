<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

class Random implements RandomInterface
{
    public function get(int $length): string
    {
        return sodium_bin2hex(random_bytes($length));
    }
}
