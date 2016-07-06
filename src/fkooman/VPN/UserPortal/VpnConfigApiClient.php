<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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

class VpnConfigApiClient extends VpnApiClient
{
    /** @var string */
    private $vpnConfigApiUri;

    public function __construct(Client $client, $vpnConfigApiUri)
    {
        parent::__construct($client);
        $this->vpnConfigApiUri = $vpnConfigApiUri;
    }

    public function addConfiguration($userId, $configName)
    {
        $vpnConfigName = sprintf('%s_%s', $userId, $configName);
        $requestUri = sprintf('%s/certificate/', $this->vpnConfigApiUri);

        return $this->exec(
            'POST',
            $requestUri,
            [
                'body' => [
                    'common_name' => $vpnConfigName,
                    'cert_type' => 'client',
                ],
            ]
        );
    }

    public function getCertList($userId)
    {
        $requestUri = sprintf('%s/certificate/%s', $this->vpnConfigApiUri, $userId);

        return $this->exec('GET', $requestUri);
    }
}
