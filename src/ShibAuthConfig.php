<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

class ShibAuthConfig
{
    use ConfigTrait;

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    public function userIdAttribute(): string
    {
        return $this->requireString('userIdAttribute');
    }

    /**
     * @return array<string>
     */
    public function permissionAttributeList(): array
    {
        return $this->requireStringArray('permissionAttributeList', []);
    }
}
