<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\WireGuard;

use LC\Portal\FileIO;

/**
 * Manages the WireGuard private keys for the profiles.
 */
class WgKeyPool
{
    private string $keyDir;

    public function __construct(string $keyDir)
    {
        $this->keyDir = $keyDir;
    }

    public function get(string $profileId): string
    {
        $wgKeyFile = $this->keyDir.'/'.$profileId.'.wg';
        if (!FileIO::exists($wgKeyFile)) {
            FileIO::writeFile($wgKeyFile, self::generatePrivateKey());
        }

        return FileIO::readFile($wgKeyFile);
    }

    /**
     * XXX duplicate in Wg.php.
     */
    private static function generatePrivateKey(): string
    {
        ob_start();
        passthru('/usr/bin/wg genkey');

        return trim(ob_get_clean());
    }
}
