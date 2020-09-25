<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\OAuth\Server\ClientDbInterface;
use fkooman\OAuth\Server\ClientInfo;
use LC\Common\Config;

class ClientFetcher implements ClientDbInterface
{
    /** @var \LC\Common\Config */
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
        if (null === $clientInfoData = $this->config->s('Api')->s('consumerList')->optionalArray($clientId)) {
            // no OAuth client with this client_id in the local configuration
            return OAuthClientInfo::getClient($clientId);
        }
        // XXX not sure why this is here?
        $redirectUriList = [];
        if (\array_key_exists('redirect_uri_list', $clientInfoData)) {
            $redirectUriList = $clientInfoData['redirect_uri_list'];
        }

        return new ClientInfo(
            $clientId,
            $redirectUriList,
            \array_key_exists('client_secret', $clientInfoData) ? $clientInfoData['client_secret'] : null,
            \array_key_exists('display_name', $clientInfoData) ? $clientInfoData['display_name'] : null,
            \array_key_exists('require_approval', $clientInfoData) ? $clientInfoData['require_approval'] : true
        );
    }
}
