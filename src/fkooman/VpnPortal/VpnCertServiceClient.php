<?php

namespace fkooman\VpnPortal;

use Guzzle\Http\Client;

class VpnCertServiceClient
{
    /** @var Guzzle\Http\Client */
    private $client;

    /** @var string */
    private $vpnCertServiceUri;

    public function __construct(Client $client, $vpnCertServiceUri)
    {
        $this->client = $client;
        $this->vpnCertServiceUri = $vpnCertServiceUri;
    }

    public function addConfiguration($userId, $configName)
    {
        $vpnConfigName = sprintf('%s_%s', $userId, $configName);
        $requestUri = sprintf('%s/config/', $this->vpnCertServiceUri);

        return $this->client->post($requestUri)
            ->setPostField('commonName', $vpnConfigName)
            ->send()
            ->getBody();
    }

    public function revokeConfiguration($userId, $configName)
    {
        $vpnConfigName = sprintf('%s_%s', $userId, $configName);
        $requestUri = sprintf('%s/config/%s', $this->vpnCertServiceUri, $vpnConfigName);

        return $this->client->delete($requestUri)
            ->send()
            ->getBody();
    }
}
