<?php

namespace fkooman\VpnPortal;

use GuzzleHttp\Client;

class VpnCertServiceClient
{
    /** @var GuzzleHttp\Client */
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
        $vpnConfigName = sprintf("%s_%s", $userId, $configName);

        return $this->client->post(
            sprintf('%s/config/', $this->vpnCertServiceUri),
            array(
                "body" => array(
                    "commonName" => $vpnConfigName,
                ),
            )
        )->getBody();
    }

    public function revokeConfiguration($userId, $configName)
    {
        $vpnConfigName = sprintf("%s_%s", $userId, $configName);

        return $this->client->delete(
            sprintf('%s/config/%s',
                $this->vpnCertServiceUri,
                $vpnConfigName
            )
        )->getBody();
    }
}
