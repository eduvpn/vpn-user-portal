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

class VpnServerApiClient extends VpnApiClient
{
    /** @var string */
    private $vpnServerApiUri;

    public function __construct(Client $client, $vpnServerApiUri)
    {
        parent::__construct($client);
        $this->vpnServerApiUri = $vpnServerApiUri;
    }

    public function getDisabledCommonNames()
    {
        $requestUri = sprintf('%s/common_names/disabled', $this->vpnServerApiUri);

        return $this->exec('GET', $requestUri)['data']['common_names'];
    }

    public function getOtpSecret($userId)
    {
        $requestUri = sprintf('%s/users/otp_secrets/%s', $this->vpnServerApiUri, $userId);

        return $this->exec('GET', $requestUri)['data']['otp_secret'];
    }

    public function setOtpSecret($userId, $otpSecret)
    {
        $requestUri = sprintf('%s/users/otp_secrets/%s', $this->vpnServerApiUri, $userId);

        return $this->exec(
            'POST',
            $requestUri,
            [
                'body' => ['otp_secret' => $otpSecret],
            ]
        )['data']['ok'];
    }

    public function hasVootToken($userId)
    {
        $requestUri = sprintf('%s/users/voot_tokens/%s', $this->vpnServerApiUri, $userId);

        return $this->exec(
            'GET',
            $requestUri
        )['data']['voot_token'];
    }

    public function setVootToken($userId, $vootToken)
    {
        $requestUri = sprintf('%s/users/voot_tokens/%s', $this->vpnServerApiUri, $userId);

        return $this->exec(
            'POST',
            $requestUri,
            [
                'body' => ['voot_token' => $vootToken],
            ]
        )['data']['ok'];
    }

    public function getUserGroups($userId)
    {
        $requestUri = sprintf('%s/users/groups/%s', $this->vpnServerApiUri, $userId);

        return $this->exec(
            'GET',
            $requestUri
        )['data']['groups'];
    }

    public function killCommonName($commonName)
    {
        $requestUri = sprintf('%s/openvpn/kill', $this->vpnServerApiUri);

        return $this->exec(
            'POST',
            $requestUri,
            [
                'body' => [
                    'common_name' => $commonName,
                ],
            ]
        )['data']['ok'];
    }

    public function disableCommonName($commonName)
    {
        $requestUri = sprintf('%s/common_names/disabled/%s', $this->vpnServerApiUri, $commonName);

        return $this->exec('POST', $requestUri)['data']['ok'];
    }

    public function getServerPools()
    {
        $requestUri = sprintf('%s/info/server', $this->vpnServerApiUri);

        return $this->exec('GET', $requestUri)['data']['pools'];
    }
}
