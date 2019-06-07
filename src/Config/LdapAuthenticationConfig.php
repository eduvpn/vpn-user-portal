<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

class LdapAuthenticationConfig extends Config
{
    public function getLdapUri(): string
    {
        return $this->requireString('ldapUri');
    }

    public function getBindDnTemplate(): string
    {
        return $this->requireString('bindDnTemplate');
    }

    /**
     * @return array<string>
     */
    public function getPermissionAttributeList(): array
    {
        if (null === $configValue = $this->optionalStringArray('permissionAttributeList')) {
            return [];
        }

        return $configValue;
    }

    public function getBaseDn(): ?string
    {
        return $this->optionalString('baseDn');
    }

    public function getUserFilterTemplate(): ?string
    {
        return $this->optionalString('userFilterTemplate');
    }
}
