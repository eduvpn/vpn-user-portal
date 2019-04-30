<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use RuntimeException;

class TlsAuth
{
    /** @var string */
    private $dataDir;

    /**
     * @param string $dataDir
     */
    public function __construct($dataDir)
    {
        $this->dataDir = $dataDir;
    }

    /**
     * @return void
     */
    public function init()
    {
        $taFile = sprintf('%s/ta.key', $this->dataDir);

        // generate the TA file if it does not exist
        if (false === FileIO::exists($taFile)) {
            $this->execOpenVpn(['--genkey', '--secret', $taFile]);
        }
    }

    /**
     * @return string
     */
    public function get()
    {
        $taFile = sprintf('%s/ta.key', $this->dataDir);

        return FileIO::readFile($taFile);
    }

    /**
     * @param array<int, string> $argv
     *
     * @return void
     */
    private function execOpenVpn(array $argv)
    {
        $command = sprintf(
            '/usr/sbin/openvpn %s >/dev/null 2>/dev/null',
            implode(' ', $argv)
        );

        exec(
            $command,
            $commandOutput,
            $returnValue
        );

        if (0 !== $returnValue) {
            throw new RuntimeException(
                sprintf('command "%s" did not complete successfully: "%s"', $command, implode(PHP_EOL, $commandOutput))
            );
        }
    }
}
