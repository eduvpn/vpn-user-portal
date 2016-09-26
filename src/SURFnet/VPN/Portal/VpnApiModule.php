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

use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Exception\HttpException;
use SURFnet\VPN\Common\HttpClient\CaClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Http\Response;

class VpnApiModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \SURFnet\VPN\Common\HttpClient\CaClient */
    private $caClient;

    public function __construct(ServerClient $serverClient, CaClient $caClient)
    {
        $this->serverClient = $serverClient;
        $this->caClient = $caClient;
    }

    public function init(Service $service)
    {
        // Add a configuration
        $service->post(
            '/config/',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $configName = $request->getPostParameter('configName');
                InputValidation::configName($configName);
                $poolId = $request->getPostParameter('poolId');
                InputValidation::poolId($poolId);

                return $this->getConfig($request->getServerName(), $poolId, $userId, $configName);
            }
        );
    }

    private function getConfig($serverName, $poolId, $userId, $configName)
    {
        // check that a certificate does not yet exist with this configName
        $userCertificateList = $this->caClient->userCertificateList($userId);
        foreach ($userCertificateList as $userCertificate) {
            if ($configName === $userCertificate['name']) {
                throw new HttpException(sprintf('a configuration with the name "%s" already exists', $configName), 400);
            }
        }

        // create a certificate
        $clientCertificate = $this->caClient->addClientCertificate($userId, $configName);

        // obtain information about this pool to be able to construct
        // a client configuration file
        $poolData = $this->serverClient->serverPool($poolId);

        $clientConfig = ClientConfig::get($poolData, $clientCertificate, false);

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setBody($clientConfig);

        return $response;
    }
}
