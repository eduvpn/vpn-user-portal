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
use SURFnet\VPN\Common\Http\ApiResponse;
use DateTime;
use DateTimeZone;

class VpnApiModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \SURFnet\VPN\Common\HttpClient\CaClient */
    private $caClient;

    /** @var bool */
    private $shuffleHosts;

    public function __construct(ServerClient $serverClient, CaClient $caClient)
    {
        $this->serverClient = $serverClient;
        $this->caClient = $caClient;
        $this->shuffleHosts = true;
    }

    public function setShuffleHosts($shuffleHosts)
    {
        $this->shuffleHosts = (bool) $shuffleHosts;
    }

    public function init(Service $service)
    {
        $service->get(
            '/profile_list',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $instanceConfig = $this->serverClient->instanceConfig();
                $serverProfiles = $instanceConfig['vpnProfiles'];
                $userGroups = $this->serverClient->userGroups($userId);
                $profileList = [];

                foreach ($serverProfiles as $profileId => $profileData) {
                    if ($profileData['enableAcl']) {
                        // is the user member of the aclGroupList?
                        if (!self::isMember($userGroups, $profileData['aclGroupList'])) {
                            continue;
                        }
                    }

                    $profileList[] = [
                        'profile_id' => $profileId,
                        'display_name' => $profileData['displayName'],
                        'two_factor' => $profileData['twoFactor'],
                    ];
                }

                return new ApiResponse('profile_list', $profileList);
            }
        );

        $service->post(
            '/create_config',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $configName = $request->getPostParameter('config_name');
                InputValidation::configName($configName);
                $profileId = $request->getPostParameter('profile_id');
                InputValidation::profileId($profileId);

                return $this->getConfig($request->getServerName(), $profileId, $userId, $configName);
            }
        );

        $service->get(
            '/user_messages',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                // check if the user is disabled, show this then
                $msgList = [];
                if ($this->serverClient->isDisabledUser($userId)) {
                    $dateTime = new DateTime();
                    $dateTime->setTimeZone(new DateTimeZone('UTC'));
                    $msgList[] = [
                        'type' => 'notification',
                        'date' => $dateTime->format('Y-m-d\TH:i:s\Z'),
                        'content' => 'Your account has been disabled. Please contact support.',
                    ];
                }

                return new ApiResponse(
                    'user_messages',
                    $msgList
                );
            }
        );

        $service->get(
            '/system_messages',
            function (Request $request, array $hookData) {
                return new ApiResponse(
                    'system_messages',
                    [
                    ]
                );
            }
        );
    }

    private function getConfig($serverName, $profileId, $userId, $configName)
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

        // obtain information about this profile to be able to construct
        // a client configuration file
        $profileData = $this->serverClient->serverProfile($profileId);

        $clientConfig = ClientConfig::get($profileData, $clientCertificate, $this->shuffleHosts);

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setBody($clientConfig);

        return $response;
    }

    private static function isMember(array $userGroups, array $aclGroupList)
    {
        // if any of the groups in userGroups is part of aclGroupList return
        // true, otherwise false
        foreach ($userGroups as $userGroup) {
            if (in_array($userGroup['id'], $aclGroupList)) {
                return true;
            }
        }

        return false;
    }
}
