<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use RuntimeException;

class Environment
{
    public static function verify(): void
    {
        // @see https://www.php.net/manual/en/mbstring.configuration.php#ini.mbstring.func-overload
        if (false !== ini_get('mbstring.func_overload')) {
            throw new RuntimeException('"mbstring.func_overload" MUST NOT be enabled');
        }
    }
}
