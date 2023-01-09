<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

class Environment
{
    /**
     * @return array<string>
     */
    public static function verify(): array
    {
        $problemList = [];
        // @see https://www.php.net/manual/en/mbstring.configuration.php#ini.mbstring.func-overload
        if (false !== (bool) ini_get('mbstring.func_overload')) {
            $problemList[] = '"mbstring.func_overload" MUST NOT be enabled';
        }

        return $problemList;
    }
}
