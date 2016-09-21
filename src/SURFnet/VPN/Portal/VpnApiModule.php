<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace SURFnet\VPN\Portal;

use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use SURFnet\VPN\Common\Api\VpnCaApiClient;
use SURFnet\VPN\Common\Api\VpnServerApiClient;

class VpnApiModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\Api\VpnCaApiClient */
    private $vpnCaClient;

    /** @var \SURFnet\VPN\Common\Api\VpnServerApiClient */
    private $vpnServerApiClient;

    public function __construct(VpnCaApiClient $vpnCaApiClient, VpnServerApiClient $vpnServerApiClient)
    {
        $this->vpnCaApiClient = $vpnCaApiClient;
        $this->vpnServerApiClient = $vpnServerApiClient;
    }

    public function init(Service $service)
    {
        // Add a configuration
        $service->post(
            '/api/config',
            function (Request $request, UserInfoInterface $userInfo) {
                $configName = $request->getPostParameter('name');
                Utils::validateConfigName($configName);

                return $this->addConfig($userInfo, $configName);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('api'),
                ),
            )
        );
    }

    private function addConfig(UserInfoInterface $userInfo, $configName)
    {
        $certData = $this->vpnCaApiClient->addConfiguration($userInfo->getUserId(), $configName);
        $serverPools = $this->vpnServerApiClient->getServerPools();
        $serverInfo = $serverPools[0];

        $remoteEntities = [];
        foreach ($serverInfo['instances'] as $instance) {
            $remoteEntities[] = [
                'port' => $instance['port'],
                'proto' => $instance['proto'],
                'host' => $serverInfo['hostName'],
            ];
        }
        $remoteEntities = ['remote' => $remoteEntities];

        $clientConfig = new ClientConfig();
        $vpnConfig = implode(PHP_EOL, $clientConfig->get(array_merge(['twoFactor' => $serverInfo['twoFactor']], $certData['certificate'], $remoteEntities)));

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setBody($vpnConfig);

        return $response;
    }
}
