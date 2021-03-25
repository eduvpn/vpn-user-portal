<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

class TlsCrypt
{
    private string $keyDir;

    public function __construct(string $keyDir)
    {
        $this->keyDir = $keyDir;
    }

    public function get(string $profileId): string
    {
        // check whether we still have global legacy "ta.key". Use it if we
        // find it...
        $globalTlsCryptKey = sprintf('%s/ta.key', $this->keyDir);
        if (@file_exists($globalTlsCryptKey)) {
            return FileIO::readFile($globalTlsCryptKey);
        }

        // check whether we already have profile tls-crypt key...
        $profileTlsCryptKey = sprintf('%s/tls-crypt-%s.key', $this->keyDir, $profileId);
        if (@file_exists($profileTlsCryptKey)) {
            return FileIO::readFile($profileTlsCryptKey);
        }

        // no key yet, create one
        $tlsCryptKey = self::generate();
        FileIO::writeFile($profileTlsCryptKey, $tlsCryptKey);

        return FileIO::readFile($profileTlsCryptKey);
    }

    private static function generate(): string
    {
        // Same as $(openvpn --genkey --secret <file>)
        $randomData = wordwrap(sodium_bin2hex(random_bytes(256)), 32, "\n", true);
        $tlsCrypt = <<< EOF
            #
            # 2048 bit OpenVPN static key
            #
            -----BEGIN OpenVPN Static key V1-----
            $randomData
            -----END OpenVPN Static key V1-----
            EOF;

        return $tlsCrypt;
    }
}
