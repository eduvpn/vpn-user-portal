<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\WireGuard;

class Key
{
    public static function generatePrivateKey(): string
    {
        ob_start();
        passthru('/usr/bin/wg genkey');

        return trim(ob_get_clean());
    }

    public static function extractPublicKey(string $privateKey): string
    {
        ob_start();
        passthru("echo {$privateKey} | /usr/bin/wg pubkey");

        return trim(ob_get_clean());
    }
}
