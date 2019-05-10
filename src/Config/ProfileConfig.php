<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

class ProfileConfig extends Config
{
    /**
     * @return int
     */
    public function getProfileNumber()
    {
        return $this->requireInt('profileNumber');
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return $this->requireString('displayName');
    }

    /**
     * @return string
     */
    public function getRangeFour()
    {
        return $this->requireString('rangeFour');
    }

    /**
     * @return string
     */
    public function getRangeSix()
    {
        return $this->requireString('rangeSix');
    }

    /**
     * @return string
     */
    public function getHostname()
    {
        return $this->requireString('hostName');
    }

    /**
     * @return bool
     */
    public function getDefaultGateway()
    {
        if (null === $configValue = $this->optionalBool('defaultGateway')) {
            return true;
        }

        return $configValue;
    }

    /**
     * @return array<string>
     */
    public function getRoutes()
    {
        if (null === $configValue = $this->optionalStringArray('routes')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return array<string>
     */
    public function getDns()
    {
        if (null === $configValue = $this->optionalStringArray('dns')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return bool
     */
    public function getClientToClient()
    {
        if (null === $configValue = $this->optionalBool('clientToClient')) {
            return false;
        }

        return $configValue;
    }

    /**
     * @return string
     */
    public function getListen()
    {
        if (null === $configValue = $this->optionalString('listen')) {
            return '::';
        }

        return $configValue;
    }

    /**
     * @return bool
     */
    public function getEnableLog()
    {
        if (null === $configValue = $this->optionalBool('enableLog')) {
            return false;
        }

        return $configValue;
    }

    /**
     * @return bool
     */
    public function getEnableAcl()
    {
        if (null === $configValue = $this->optionalBool('enableAcl')) {
            return false;
        }

        return $configValue;
    }

    /**
     * @return array<string>
     */
    public function getAclPermissionList()
    {
        if (null === $configValue = $this->optionalStringArray('aclPermissionList')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return string
     */
    public function getManagementIp()
    {
        if (null === $configValue = $this->optionalString('managementIp')) {
            return '127.0.0.1';
        }

        return $configValue;
    }

    /**
     * @return array<string>
     */
    public function getVpnProtoPortList()
    {
        if (null === $configValue = $this->optionalStringArray('vpnProtoPortList')) {
            return ['udp/1194', 'tcp/1194'];
        }

        return $configValue;
    }

    /**
     * @return array<string>
     */
    public function getExposedVpnProtoPortList()
    {
        if (null === $configValue = $this->optionalStringArray('exposedVpnProtoPortList')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return bool
     */
    public function getHideProfile()
    {
        if (null === $configValue = $this->optionalBool('hideProfile')) {
            return false;
        }

        return $configValue;
    }

    /**
     * @return bool
     */
    public function getBlockLan()
    {
        if (null === $configValue = $this->optionalBool('blockLan')) {
            return false;
        }

        return $configValue;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
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
