<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

class RadiusAuthConfig
{
    use ConfigTrait;

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    /**
     * @return array<string>
     */
    public function serverList(): array
    {
        return $this->requireStringArray('serverList');
    }

    public function radiusRealm(): ?string
    {
        return $this->optionalString('radiusRealm');
    }

    public function nasIdentifier(): ?string
    {
        return $this->optionalString('nasIdentifier');
    }

    public function permissionAttribute(): ?int
    {
        return $this->optionalInt('permissionAttribute');
    }
}
