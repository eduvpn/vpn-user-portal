<?php

namespace fkooman\VPN\UserPortal;

use GuzzleHttp\Client;

class VpnConfigApiClient
{
    /** @var \GuzzleHttp\Client */
    private $client;

    /** @var string */
    private $vpnConfigApiUri;

    public function __construct(Client $client, $vpnConfigApiUri)
    {
        $this->client = $client;
        $this->vpnConfigApiUri = $vpnConfigApiUri;
    }

    public function addConfiguration($userId, $configName)
    {
        $vpnConfigName = sprintf('%s_%s', $userId, $configName);
        $requestUri = sprintf('%s/config/', $this->vpnConfigApiUri);

        return $this->client->post(
            $requestUri,
            array(
                'body' => array('commonName' => $vpnConfigName),
            )
        )->getBody();
    }

    public function revokeConfiguration($userId, $configName)
    {
        $vpnConfigName = sprintf('%s_%s', $userId, $configName);
        $requestUri = sprintf('%s/config/%s', $this->vpnConfigApiUri, $vpnConfigName);

        return $this->client->delete($requestUri)->getBody();
    }

    public function getCertList($userId)
    {
        $requestUri = sprintf('%s/config?userId=%s', $this->vpnConfigApiUri, $userId);

        return $this->client->get($requestUri)->json();
    }
}
