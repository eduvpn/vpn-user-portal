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
    public function useMemcached(): bool
    {
        return $this->requireBool('useMemcached', false);
    }

    /**
     * @return array<array{h:string,p:int}>
     */
    public function memcachedServerList(): array
    {
        $memcachedServerList = $this->requireStringArray('memcachedServerList', []);
        $returnMemcachedServerList = [];
        foreach ($memcachedServerList as $memcachedServer) {
            [$h, $p] = explode(':', $memcachedServer, 2);
            $returnMemcachedServerList[] = [
                'h' => $h,
                'p' => (int) $p,
            ];
        }

        return $returnMemcachedServerList;
    }
}
