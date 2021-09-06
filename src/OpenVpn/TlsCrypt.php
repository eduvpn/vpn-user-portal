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
use LC\Portal\Validator;

class TlsCrypt
{
    private string $keyDir;

    public function __construct(string $keyDir)
    {
        $this->keyDir = $keyDir;
    }

    public function get(string $profileId): string
    {
        // validate profileId also here, to make absolutely sure...
        Validator::profileId($profileId);

        // if we have "tls-crypt.key" we'll use that for all profiles, if not,
        // we use the profile specific ones
        if (null !== $tlsCryptKey = FileIO::readFileIfExists($this->keyDir.'/tls-crypt.key')) {
            return $tlsCryptKey;
        }

        // profile specific tls-crypt file
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
