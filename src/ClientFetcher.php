<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Server\ClientInfo;
use SURFnet\VPN\Common\Config;

class ClientFetcher
{
    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $clientId
     *
     * @return false|\fkooman\OAuth\Server\ClientInfo
     */
    public function get($clientId)
    {
        if (false === $this->config->getSection('Api')->getSection('consumerList')->hasItem($clientId)) {
            // if not in configuration file, check if it is in the hardcoded list
            return OAuthClientInfo::getClient($clientId);
        }

        // XXX switch to only support 'redirect_uri_list' for 2.0
        $clientInfoData = $this->config->getSection('Api')->getSection('consumerList')->getItem($clientId);
        $redirectUriList = [];
        if (array_key_exists('redirect_uri_list', $clientInfoData)) {
            $redirectUriList = array_merge($redirectUriList, (array) $clientInfoData['redirect_uri_list']);
        }
        if (array_key_exists('redirect_uri', $clientInfoData)) {
            $redirectUriList = array_merge($redirectUriList, (array) $clientInfoData['redirect_uri']);
        }
        $clientInfoData['redirect_uri_list'] = $redirectUriList;

        return new ClientInfo($clientInfoData);
    }
}
