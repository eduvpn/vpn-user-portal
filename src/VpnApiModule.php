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

use DateTime;
use DateTimeZone;
use SURFnet\VPN\Common\Http\ApiErrorResponse;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\ProfileConfig;

class VpnApiModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var bool */
    private $shuffleHosts;

    public function __construct(ServerClient $serverClient)
    {
        $this->serverClient = $serverClient;
        $this->shuffleHosts = true;
    }

    public function setShuffleHosts($shuffleHosts)
    {
        $this->shuffleHosts = (bool) $shuffleHosts;
    }

    public function init(Service $service)
    {
        // API 1, 2
        $service->get(
            '/profile_list',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $profileList = $this->serverClient->get('profile_list');
                $userGroups = $this->serverClient->get('user_groups', ['user_id' => $userId]);

                $userProfileList = [];
                foreach ($profileList as $profileId => $profileData) {
                    $profileConfig = new ProfileConfig($profileData);
                    if ($profileConfig->getItem('enableAcl')) {
                        // is the user member of the aclGroupList?
                        if (!self::isMember($userGroups, $profileConfig->getSection('aclGroupList')->toArray())) {
                            continue;
                        }
                    }

                    $userProfileList[] = [
                        'profile_id' => $profileId,
                        'display_name' => $profileConfig->getItem('displayName'),
                        'two_factor' => $profileConfig->getItem('twoFactor'),
                    ];
                }

                return new ApiResponse('profile_list', $userProfileList);
            }
        );

        // API 2
        $service->post(
            '/create_certificate',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $displayName = InputValidation::displayName($request->getPostParameter('display_name'));

                $clientCertificate = $this->getCertificate($userId, $displayName);

                return new ApiResponse(
                    'create_certificate',
                    [
                        'certificate' => $clientCertificate['certificate'],
                        'private_key' => $clientCertificate['private_key'],
                    ]
                );
            }
        );

        // API 2
        $service->get(
            '/profile_config',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $requestedProfileId = InputValidation::profileId($request->getQueryParameter('profile_id'));

                $profileList = $this->serverClient->get('profile_list');
                $userGroups = $this->serverClient->get('user_groups', ['user_id' => $userId]);

                $availableProfiles = [];
                foreach ($profileList as $profileId => $profileData) {
                    $profileConfig = new ProfileConfig($profileData);
                    if ($profileConfig->getItem('enableAcl')) {
                        // is the user member of the aclGroupList?
                        if (!self::isMember($userGroups, $profileConfig->getSection('aclGroupList')->toArray())) {
                            continue;
                        }
                    }

                    $availableProfiles[] = $profileId;
                }

                if (!in_array($requestedProfileId, $availableProfiles)) {
                    return new ApiErrorResponse('profile_config', 'user has no access to this profile');
                }

                return $this->getConfigOnly($requestedProfileId);
            }
        );

        // API 1
        $service->post(
            '/create_config',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $displayName = InputValidation::displayName($request->getPostParameter('display_name'));
                $profileId = InputValidation::profileId($request->getPostParameter('profile_id'));

                return $this->getConfig($request->getServerName(), $profileId, $userId, $displayName);
            }
        );

        // API 1, 2
        $service->get(
            '/user_messages',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $msgList = [];

                return new ApiResponse(
                    'user_messages',
                    $msgList
                );
            }
        );

        // API 1, 2
        $service->get(
            '/system_messages',
            function (Request $request, array $hookData) {
                $msgList = [];

                $motdMessages = $this->serverClient->get('system_messages', ['message_type' => 'motd']);
                foreach ($motdMessages as $motdMessage) {
                    $dateTime = new DateTime($motdMessage['date_time']);
                    $dateTime->setTimeZone(new DateTimeZone('UTC'));

                    $msgList[] = [
                        // no support yet for 'motd' type in application API
                        'type' => 'notification',
                        'date_time' => $dateTime->format('Y-m-d\TH:i:s\Z'),
                        'message' => $motdMessage['message'],
                    ];
                }

                return new ApiResponse(
                    'system_messages',
                    $msgList
                );
            }
        );
    }

    // API 1
    private function getConfig($serverName, $profileId, $userId, $displayName)
    {
        // obtain information about this profile to be able to construct
        // a client configuration file
        $profileList = $this->serverClient->get('profile_list');
        $profileData = $profileList[$profileId];

        // create a certificate
        $clientCertificate = $this->getCertificate($userId, $displayName);
        // get the CA & tls-auth
        $serverInfo = $this->serverClient->get('server_info');

        $clientConfig = ClientConfig::get($profileData, $serverInfo, $clientCertificate, $this->shuffleHosts);
        $clientConfig = str_replace("\n", "\r\n", $clientConfig);

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setBody($clientConfig);

        return $response;
    }

    // API 2
    private function getConfigOnly($profileId)
    {
        // obtain information about this profile to be able to construct
        // a client configuration file
        $profileList = $this->serverClient->get('profile_list');
        $profileData = $profileList[$profileId];

        // get the CA & tls-auth
        $serverInfo = $this->serverClient->get('server_info');

        $clientConfig = ClientConfig::get($profileData, $serverInfo, [], $this->shuffleHosts);
        $clientConfig = str_replace("\n", "\r\n", $clientConfig);

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setBody($clientConfig);

        return $response;
    }

    private function getCertificate($userId, $displayName)
    {
        // create a certificate
        return $this->serverClient->post(
            'add_client_certificate',
            [
                'user_id' => $userId,
                'display_name' => $displayName,
            ]
        );
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
