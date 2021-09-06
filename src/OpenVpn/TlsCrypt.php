<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OpenVpn;

use DomainException;
use LC\Portal\FileIO;
use RuntimeException;

class TlsCrypt
{
    private string $keyDir;

    public function __construct(string $keyDir)
    {
        $this->keyDir = $keyDir;
    }

    public function get(string $profileId): string
    {
        // make absolutely sure profileId is safe to use
        if (1 !== preg_match('/^[a-zA-Z0-9-.]+$/', $profileId)) {
            throw new DomainException('invalid "profileId"');
        }

        // if we have "tls-crypt.key" we'll use that for all profiles, if not,
        // we use the profile specific ones
        if (null !== $tlsCryptKey = FileIO::readFileIfExists($this->keyDir.'/tls-crypt.key')) {
            return $tlsCryptKey;
        }

        if (null !== $tlsCryptKey = FileIO::readFileIfExists($this->keyDir.'/tls-crypt-'.$profileId.'.key')) {
            return $tlsCryptKey;
        }

        // create key
        // NOTE: this requires OpenVPN 2.5
        self::exec('/usr/sbin/openvpn --genkey tls-crypt --secret '.$this->keyDir.'/tls-crypt-'.$profileId.'.key');

        return FileIO::readFile($this->keyDir.'/tls-crypt-'.$profileId.'.key');
    }

    private static function exec(string $execCmd): void
    {
        exec(
            sprintf('%s 2>&1', $execCmd),
            $commandOutput,
            $returnValue
        );

        if (0 !== $returnValue) {
            throw new RuntimeException(sprintf('command "%s" did not complete successfully: "%s"', $execCmd, implode(PHP_EOL, $commandOutput)));
        }
    }
}
