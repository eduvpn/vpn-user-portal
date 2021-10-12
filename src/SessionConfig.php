<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

class SessionConfig
{
    use ConfigTrait;

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    /**
     * Whether or not to enable MemCache support.
     */
    public function useMemCache(): bool
    {
        return $this->requireBool('useMemCache', false);
    }

    /**
     * @return array<array{host:string,port:int}>
     */
    public function memCacheServerList(): array
    {
        $serverList = $this->requireStringArray('memCacheServerList', []);
        $returnServerList = [];
        foreach ($serverList as $server) {
            [$host, $port] = explode(':', $server, 2);
            $returnServerList[] = [
                'host' => (string) $host,
                'port' => (int) $port,
            ];
        }

        error_log(var_export($returnServerList, true));

        return $returnServerList;
    }
}
