<?php
/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace fkooman\VPN\UserPortal;

use GuzzleHttp\Client;

class VpnServerApiClient
{
    /** @var \GuzzleHttp\Client */
    private $client;

    /** @var string */
    private $vpnServerApiUri;

    public function __construct(Client $client, $vpnServerApiUri)
    {
        $this->client = $client;
        $this->vpnServerApiUri = $vpnServerApiUri;
    }

    public function getConnections()
    {
        $requestUri = sprintf('%s/connections', $this->vpnServerApiUri);

        return $this->client->get($requestUri)->json();
    }

    public function getServers()
    {
        $requestUri = sprintf('%s/servers', $this->vpnServerApiUri);

        return $this->client->get($requestUri)->json();
    }

    public function disableCommonName($commonName)
    {
        $requestUri = sprintf('%s/disableCommonName', $this->vpnServerApiUri);

        return $this->client->post(
            $requestUri,
            array(
                'body' => array(
                    'common_name' => $commonName,
                ),
            )
        )->getBody();
    }

    public function enableCommonName($commonName)
    {
        $requestUri = sprintf('%s/enableCommonName', $this->vpnServerApiUri);

        return $this->client->post(
            $requestUri,
            array(
                'body' => array(
                    'common_name' => $commonName,
                ),
            )
        )->getBody();
    }

    public function getDisabledCommonNames()
    {
        $requestUri = sprintf('%s/disabledCommonNames', $this->vpnServerApiUri);

        return $this->client->get($requestUri)->json();
    }

    public function postKillClient($id, $commonName)
    {
        $requestUri = sprintf('%s/kill', $this->vpnServerApiUri);

        return $this->client->post(
            $requestUri,
            array(
                'body' => array(
                    'id' => $id,
                    'common_name' => $commonName,
                ),
            )
        )->getBody();
    }

    public function postRefreshCrl()
    {
        $requestUri = sprintf('%s/refreshCrl', $this->vpnServerApiUri);

        return $this->client->post($requestUri)->getBody();
    }
}
