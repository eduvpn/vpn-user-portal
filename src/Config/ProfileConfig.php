<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

class ProfileConfig extends Config
{
    public function getProfileNumber(): int
    {
        return $this->requireInt('profileNumber');
    }

    public function getDisplayName(): string
    {
        return $this->requireString('displayName');
    }

    public function getRangeFour(): string
    {
        return $this->requireString('rangeFour');
    }

    public function getRangeSix(): string
    {
        return $this->requireString('rangeSix');
    }

    public function getHostname(): string
    {
        return $this->requireString('hostName');
    }

    public function getDefaultGateway(): bool
    {
        if (null === $configValue = $this->optionalBool('defaultGateway')) {
            return true;
        }

        return $configValue;
    }

    /**
     * @return array<string>
     */
    public function getRoutes(): array
    {
        if (null === $configValue = $this->optionalStringArray('routes')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return array<string>
     */
    public function getDns(): array
    {
        if (null === $configValue = $this->optionalStringArray('dns')) {
            return [];
        }

        return $configValue;
    }

    public function getClientToClient(): bool
    {
        if (null === $configValue = $this->optionalBool('clientToClient')) {
            return false;
        }

        return $configValue;
    }

    public function getListen(): string
    {
        if (null === $configValue = $this->optionalString('listen')) {
            return '::';
        }

        return $configValue;
    }

    public function getEnableLog(): bool
    {
        if (null === $configValue = $this->optionalBool('enableLog')) {
            return false;
        }

        return $configValue;
    }

    public function getEnableAcl(): bool
    {
        if (null === $configValue = $this->optionalBool('enableAcl')) {
            return false;
        }

        return $configValue;
    }

    /**
     * @return array<string>
     */
    public function getAclPermissionList(): array
    {
        if (null === $configValue = $this->optionalStringArray('aclPermissionList')) {
            return [];
        }

        return $configValue;
    }

    public function getManagementIp(): string
    {
        if (null === $configValue = $this->optionalString('managementIp')) {
            return '127.0.0.1';
        }

        return $configValue;
    }

    /**
     * @return array<string>
     */
    public function getVpnProtoPortList(): array
    {
        if (null === $configValue = $this->optionalStringArray('vpnProtoPortList')) {
            return ['udp/1194', 'tcp/1194'];
        }

        return $configValue;
    }

    /**
     * @return array<string>
     */
    public function getExposedVpnProtoPortList(): array
    {
        if (null === $configValue = $this->optionalStringArray('exposedVpnProtoPortList')) {
            return [];
        }

        return $configValue;
    }

    public function getHideProfile(): bool
    {
        if (null === $configValue = $this->optionalBool('hideProfile')) {
            return false;
        }

        return $configValue;
    }

    public function getBlockLan(): bool
    {
        if (null === $configValue = $this->optionalBool('blockLan')) {
            return false;
        }

        return $configValue;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'aclPermissionList' => $this->getAclPermissionList(),
            'blockLan' => $this->getBlockLan(),
            'clientToClient' => $this->getClientToClient(),
            'defaultGateway' => $this->getDefaultGateway(),
            'displayName' => $this->getDisplayName(),
            'dns' => $this->getDns(),
            'enableAcl' => $this->getEnableAcl(),
            'enableLog' => $this->getEnableLog(),
            'exposedVpnProtoPortList' => $this->getExposedVpnProtoPortList(),
            'hideProfile' => $this->getHideProfile(),
            'hostName' => $this->getHostname(),
            'listen' => $this->getListen(),
            'managementIp' => $this->getManagementIp(),
            'profileNumber' => $this->getProfileNumber(),
            'rangeFour' => $this->getRangeFour(),
            'rangeSix' => $this->getRangeSix(),
            'routes' => $this->getRoutes(),
            'vpnProtoPortList' => $this->getVpnProtoPortList(),
        ];
    }
}
