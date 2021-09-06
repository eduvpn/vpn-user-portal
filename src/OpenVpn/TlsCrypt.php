<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OpenVpn;

use LC\Portal\FileIO;
use RangeException;

class TlsCrypt
{
    private string $keyDir;

    public function __construct(string $keyDir)
    {
        $this->keyDir = $keyDir;
    }

    public function get(string $profileId): string
    {
        // make extra sure the profileId is safe
        // XXX use Validator
        if (1 !== preg_match('/^[a-zA-Z0-9-.]+$/', $profileId)) {
            throw new RangeException('invalid "profileId"');
        }

        // check whether we still have global legacy "ta.key". Use it if we
        // find it...
        if (null !== $tlsCryptKey = FileIO::readFileIfExists($this->keyDir.'/tls-crypt.key')) {
            return $tlsCryptKey;
        }

        $tlsCryptKeyFile = $this->keyDir.'/tls-crypt-'.$profileId.'.key';
        if (null !== $tlsCryptKey = FileIO::readFileIfExists($tlsCryptKeyFile)) {
            return $tlsCryptKey;
        }

        // no key yet, create one
        FileIO::writeFile($tlsCryptKeyFile, self::generate());

        return FileIO::readFile($tlsCryptKeyFile);
    }

    private static function generate(): string
    {
        // Same as $(openvpn --genkey --secret <file>)
        $randomData = wordwrap(sodium_bin2hex(random_bytes(256)), 32, "\n", true);

        return <<< EOF
            #
            # 2048 bit OpenVPN static key
            #
            -----BEGIN OpenVPN Static key V1-----
            {$randomData}
            -----END OpenVPN Static key V1-----
            EOF;
    }
}
