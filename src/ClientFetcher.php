<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\OAuth\Server\ClientDbInterface;

class ClientFetcher implements ClientDbInterface
{
    /** @var array<string,\fkooman\OAuth\Server\ClientInfo> */
    private $clientInfoList;

    /**
     * @param array<string,\fkooman\OAuth\Server\ClientInfo> $clientInfoList
     */
    public function __construct(array $clientInfoList)
    {
        $this->clientInfoList = $clientInfoList;
    }

    /**
     * @param string $clientId
     *
     * @return false|\fkooman\OAuth\Server\ClientInfo
     */
    public function get($clientId)
    {
        if (!\array_key_exists($clientId, $this->clientInfoList)) {
            // if not in configuration file, check if it is in the hardcoded list
            return OAuthClientInfo::getClient($clientId);
        }

        return $this->clientInfoList[$clientId];
    }
}
