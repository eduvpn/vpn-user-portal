<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

use DateInterval;
use fkooman\OAuth\Server\ClientInfo;
use LC\Portal\Config\Exception\ConfigException;

class ApiConfig extends Config
{
    /**
     * @return array<string,\fkooman\OAuth\Server\ClientInfo>
     */
    public function getClientInfoList()
    {
        if (!\array_key_exists('consumerList', $this->configData)) {
            return [];
        }

        if (!\is_array($this->configData['consumerList'])) {
            throw new ConfigException('');
        }

        $clientInfoList = [];
        foreach ($this->configData['consumerList'] as $clientId => $clientInfoData) {
            $clientInfoList[$clientId] = new ClientInfo(
                $clientId,
                \array_key_exists('redirect_uri_list', $clientInfoData) ? $clientInfoData['redirect_uri_list'] : [],
                \array_key_exists('client_secret', $clientInfoData) ? $clientInfoData['client_secret'] : null,
                \array_key_exists('display_name', $clientInfoData) ? $clientInfoData['display_name'] : null,
                \array_key_exists('require_approval', $clientInfoData) ? $clientInfoData['require_approval'] : true
            );
        }

        return $clientInfoList;
    }

    /**
     * @return \DateInterval
     */
    public function getTokenExpiry()
    {
        if (null === $configValue = $this->optionalString('tokenExpiry')) {
            return new DateInterval('PT1H');
        }

        return new DateInterval($configValue);
    }

    /**
     * @return bool
     */
    public function getRemoteAccess()
    {
        if (null === $configValue = $this->optionalBool('remoteAccess')) {
            return false;
        }

        return $configValue;
    }

    /**
     * @return array<string,array<string,string>>
     */
    public function getRemoteAccessList()
    {
        return [];
    }
}
