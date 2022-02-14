<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Cfg;

class MellonAuthConfig
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

    public function nameIdSerialization(): bool
    {
        return $this->requireBool('nameIdSerialization', false);
    }

    /**
     * @return array<string>
     */
    public function permissionAttributeList(): array
    {
        return $this->requireStringArray('permissionAttributeList', []);
    }

    public function spEntityId(): string
    {
        return $this->requireString('spEntityId');
    }
}
