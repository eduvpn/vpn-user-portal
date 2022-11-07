<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use RuntimeException;
use SplFileInfo;
use Vpn\Portal\Exception\ConnectionHookException;

class ScriptConnectionHook implements ConnectionHookInterface
{
    private string $scriptPath;

    public function __construct(string $scriptPath)
    {
        $this->scriptPath = self::verifyFile($scriptPath);
    }

    /**
     * @throws \Vpn\Portal\Exception\ConnectionHookException
     */
    public function connect(string $userId, string $profileId, string $connectionId, string $ipFour, string $ipSix, ?string $originatingIp): void
    {
        $envVarList = [
            'EVENT' => 'C',
            'USER_ID' => $userId,
            'PROFILE_ID' => $profileId,
            'CONNECTION_ID' => $connectionId,
            'IP_FOUR' => $ipFour,
            'IP_SIX' => $ipSix,
        ];
        if (null !== $originatingIp) {
            $envVarList['ORIGINATING_IP'] = $originatingIp;
        }

        $this->exec(
            sprintf(
                '%s %s',
                self::prepareEnvVarList($envVarList),
                $this->scriptPath
            )
        );
    }

    /**
     * @throws \Vpn\Portal\Exception\ConnectionHookException
     */
    public function disconnect(string $userId, string $profileId, string $connectionId, string $ipFour, string $ipSix): void
    {
        $envVarList = [
            'EVENT' => 'D',
            'USER_ID' => $userId,
            'PROFILE_ID' => $profileId,
            'CONNECTION_ID' => $connectionId,
            'IP_FOUR' => $ipFour,
            'IP_SIX' => $ipSix,
        ];

        $this->exec(
            sprintf(
                '%s %s',
                self::prepareEnvVarList($envVarList),
                $this->scriptPath
            )
        );
    }

    private static function verifyFile(string $scriptPath): string
    {
        $fileInfo = new SplFileInfo($scriptPath);
        if (!$fileInfo->isFile()) {
            throw new RuntimeException(sprintf('ScriptConnectionHook: "%s" does not exist', $scriptPath));
        }
        if (!$fileInfo->isExecutable()) {
            throw new RuntimeException(sprintf('ScriptConnectionHook: "%s" is not executable', $scriptPath));
        }

        return $scriptPath;
    }

    /**
     * @param array<string,string> $envVarList
     */
    private static function prepareEnvVarList(array $envVarList): string
    {
        $envList = [];
        foreach ($envVarList as $envKey => $envValue) {
            $envList[] = 'VPN_'.$envKey.'='.escapeshellarg($envValue);
        }

        return implode(' ', $envList);
    }

    private function exec(string $execCmd): void
    {
        exec(
            sprintf('%s 2>&1', $execCmd),
            $commandOutput,
            $returnValue
        );

        if (0 !== $returnValue) {
            throw new ConnectionHookException(
                sprintf('script "%s" failed with return code: %d', $this->scriptPath, $returnValue)
            );
        }
    }
}
