<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

class SamlAuthenticationConfig extends Config
{
    public function getUserIdAttribute(): string
    {
        return $this->requireString('userIdAttribute');
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

    public function getSpEntityId(): ?string
    {
        return $this->optionalString('spEntityId');
    }

    public function getIdpMetadata(): string
    {
        return $this->requireString('idpMetadata');
    }

    public function getIdpEntityId(): ?string
    {
        return $this->optionalString('idpEntityId');
    }

    public function getDiscoUrl(): ?string
    {
        return $this->optionalString('discoUrl');
    }

    /**
     * @return array<string>
     */
    public function getAuthnContext(): array
    {
        if (null === $configValue = $this->optionalStringArray('authnContext')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return array<string,array<string>>
     */
    public function getPermissionAuthnContext(): array
    {
        if (null === $configValue = $this->optionalStringWithStringArray('permissionAuthnContext')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return array<string,string>
     */
    public function getPermissionSessionExpiry(): array
    {
        if (null === $configValue = $this->optionalStringArray('permissionSessionExpiry')) {
            return [];
        }

        return $configValue;
    }
}
