<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Cfg;

class LdapAuthConfig
{
    use ConfigTrait;

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    public function ldapUri(): string
    {
        return $this->requireString('ldapUri');
    }

    public function tlsCa(): ?string
    {
        return $this->optionalString('tlsCa');
    }

    public function tlsCert(): ?string
    {
        return $this->optionalString('tlsCert');
    }

    public function tlsKey(): ?string
    {
        return $this->optionalString('tlsKey');
    }

    public function bindDnTemplate(): ?string
    {
        return $this->optionalString('bindDnTemplate');
    }

    public function baseDn(): string
    {
        return $this->requireString('baseDn');
    }

    public function userFilterTemplate(): string
    {
        return $this->requireString('userFilterTemplate');
    }

    public function userIdAttribute(): string
    {
        return $this->requireString('userIdAttribute');
    }

    public function addRealm(): ?string
    {
        return $this->optionalString('addRealm');
    }

    /**
     * @return array<string>
     */
    public function permissionAttributeList(): array
    {
        return $this->requireStringArray('permissionAttributeList', []);
    }

    public function searchBindDn(): ?string
    {
        return $this->optionalString('searchBindDn');
    }

    public function searchBindPass(): ?string
    {
        return $this->optionalString('searchBindPass');
    }
}
