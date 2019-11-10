<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateInterval;
use DateTime;
use DateTimeZone;
use LC\Common\Config;
use LC\Common\Http\ApiErrorResponse;
use LC\Common\Http\ApiResponse;
use LC\Common\Http\Exception\InputValidationException;
use LC\Common\Http\InputValidation;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\HttpClient\ServerClient;
use LC\Common\ProfileConfig;
use LC\Portal\OAuth\VpnAccessTokenInfo;

class VpnApiModule implements ServiceModuleInterface
{
    /** @var \LC\Common\Config */
    private $config;

    /** @var \LC\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \DateInterval */
    private $sessionExpiry;

    /** @var bool */
    private $pickRemotesAtRandom = true;

    /** @var \DateTime */
    private $dateTime;

    /**
     * @param \LC\Common\Config                  $config
     * @param \LC\Common\HttpClient\ServerClient $serverClient
     * @param \DateInterval                      $sessionExpiry
     */
    public function __construct(Config $config, ServerClient $serverClient, DateInterval $sessionExpiry)
    {
        $this->config = $config;
        $this->serverClient = $serverClient;
        $this->sessionExpiry = $sessionExpiry;
        $this->dateTime = new DateTime();
    }

    /**
     * @param bool $pickRemotesAtRandom
     *
     * @return void
     */
    public function setPickRemotesAtRandom($pickRemotesAtRandom)
    {
        $this->pickRemotesAtRandom = $pickRemotesAtRandom;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        // API 1, 2
        $service->get(
            '/profile_list',
            /**
             * @return ApiResponse
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\OAuth\VpnAccessTokenInfo */
                $accessTokenInfo = $hookData['auth'];

                $profileList = $this->serverClient->getRequireArray('profile_list');
                $userPermissions = $this->getPermissionList($accessTokenInfo);
                $userProfileList = [];
                foreach ($profileList as $profileId => $profileData) {
                    $profileConfig = new ProfileConfig($profileData);
                    if ($profileConfig->getItem('hideProfile')) {
                        continue;
                    }
                    if ($profileConfig->getItem('enableAcl')) {
                        // is the user member of the aclPermissionList?
                        if (!VpnPortalModule::isMember($profileConfig->getSection('aclPermissionList')->toArray(), $userPermissions)) {
                            continue;
                        }
                    }

                    $userProfileList[] = [
                        'profile_id' => $profileId,
                        'display_name' => $profileConfig->getItem('displayName'),
                        // 2FA is now decided by vpn-user-portal setting, so
                        // we "lie" here to the client
                        'two_factor' => false,
                    ];
                }

                return new ApiResponse('profile_list', $userProfileList);
            }
        );

        // API 2
        // DEPRECATED, this whole call is useless now!
        $service->get(
            '/user_info',
            /**
             * @return ApiResponse
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\OAuth\VpnAccessTokenInfo */
                $accessTokenInfo = $hookData['auth'];

                return new ApiResponse(
                    'user_info',
                    [
                        'user_id' => $accessTokenInfo->getUserId(),
                        // as 2FA works through the portal now, lie here to the
                        // clients so they won't try to enroll the user
                        'two_factor_enrolled' => false,
                        'two_factor_enrolled_with' => [],
                        'two_factor_supported_methods' => [],
                        // if the user is disabled, the access_token no longer
                        // works, so this is bogus
                        'is_disabled' => false,
                    ]
                );
            }
        );

        // API 2
        $service->post(
            '/create_keypair',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\OAuth\VpnAccessTokenInfo */
                $accessTokenInfo = $hookData['auth'];
                try {
                    $clientCertificate = $this->getCertificate($accessTokenInfo);

                    return new ApiResponse(
                        'create_keypair',
                        [
                            'certificate' => $clientCertificate['certificate'],
                            'private_key' => $clientCertificate['private_key'],
                        ]
                    );
                } catch (InputValidationException $e) {
                    return new ApiErrorResponse('create_keypair', $e->getMessage());
                }
            }
        );

        // API 2
        $service->get(
            '/check_certificate',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\OAuth\VpnAccessTokenInfo */
                $accessTokenInfo = $hookData['auth'];
                $commonName = InputValidation::commonName($request->getQueryParameter('common_name'));
                $clientCertificateInfo = $this->serverClient->getRequireArrayOrFalse('client_certificate_info', ['common_name' => $commonName]);
                $responseData = $this->validateCertificate($clientCertificateInfo);

                return new ApiResponse(
                    'check_certificate',
                    $responseData
                );
            }
        );

        // API 2
        $service->get(
            '/profile_config',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\OAuth\VpnAccessTokenInfo */
                $accessTokenInfo = $hookData['auth'];
                try {
                    $requestedProfileId = InputValidation::profileId($request->getQueryParameter('profile_id'));
                    $profileList = $this->serverClient->getRequireArray('profile_list');
                    $userPermissions = $this->getPermissionList($accessTokenInfo);

                    $availableProfiles = [];
                    foreach ($profileList as $profileId => $profileData) {
                        $profileConfig = new ProfileConfig($profileData);
                        if ($profileConfig->getItem('enableAcl')) {
                            // is the user member of the userPermissions?
                            if (!VpnPortalModule::isMember($profileConfig->getSection('aclPermissionList')->toArray(), $userPermissions)) {
                                continue;
                            }
                        }

                        $availableProfiles[] = $profileId;
                    }

                    if (!\in_array($requestedProfileId, $availableProfiles, true)) {
                        return new ApiErrorResponse('profile_config', 'user has no access to this profile');
                    }

                    return $this->getConfigOnly($requestedProfileId);
                } catch (InputValidationException $e) {
                    return new ApiErrorResponse('profile_config', $e->getMessage());
                }
            }
        );

        // API 1, 2
        $service->get(
            '/user_messages',
            /**
             * @return ApiResponse
             */
            function (Request $request, array $hookData) {
                return new ApiResponse(
                    'user_messages',
                    []
                );
            }
        );

        // API 1, 2
        $service->get(
            '/system_messages',
            /**
             * @return ApiResponse
             */
            function (Request $request, array $hookData) {
                $msgList = [];

                $motdMessages = $this->serverClient->getRequireArray('system_messages', ['message_type' => 'motd']);
                foreach ($motdMessages as $motdMessage) {
                    $dateTime = new DateTime($motdMessage['date_time']);
                    $dateTime->setTimezone(new DateTimeZone('UTC'));

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

    /**
     * @param string $profileId
     *
     * @return Response
     */
    private function getConfigOnly($profileId)
    {
        // obtain information about this profile to be able to construct
        // a client configuration file
        $profileList = $this->serverClient->getRequireArray('profile_list');
        $profileData = $profileList[$profileId];

        // get the CA & tls-auth
        $serverInfo = $this->serverClient->getRequireArray('server_info');

        $clientConfig = ClientConfig::get($profileData, $serverInfo, [], $this->pickRemotesAtRandom);
        $clientConfig = str_replace("\n", "\r\n", $clientConfig);

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setBody($clientConfig);

        return $response;
    }

    /**
     * @param \LC\Portal\OAuth\VpnAccessTokenInfo $accessTokenInfo
     *
     * @return array
     */
    private function getCertificate(VpnAccessTokenInfo $accessTokenInfo)
    {
        // create a certificate
        return $this->serverClient->postRequireArray(
            'add_client_certificate',
            [
                'user_id' => $accessTokenInfo->getUserId(),
                // we won't show the Certificate entry anyway on the
                // "Certificates" page for certificates downloaded through the
                // API
                'display_name' => $accessTokenInfo->getClientId(),
                'client_id' => $accessTokenInfo->getClientId(),
                'expires_at' => $this->getExpiresAt($accessTokenInfo)->format(DateTime::ATOM),
            ]
        );
    }

    /**
     * @param false|array $clientCertificateInfo
     *
     * @return array<string, bool|string>
     */
    private function validateCertificate($clientCertificateInfo)
    {
        $reason = '';
        if (false === $clientCertificateInfo) {
            // certificate with this CN does not exist, was deleted by
            // user, or complete new installation of service with new
            // CA
            $isValid = false;
            $reason = 'certificate_missing';
        } elseif (new DateTime($clientCertificateInfo['valid_from']) > $this->dateTime) {
            // certificate not yet valid
            $isValid = false;
            $reason = 'certificate_not_yet_valid';
        } elseif (new DateTime($clientCertificateInfo['valid_to']) < $this->dateTime) {
            // certificate not valid anymore
            $isValid = false;
            $reason = 'certificate_expired';
        } else {
            $isValid = true;
        }

        $responseData = [
            'is_valid' => $isValid,
        ];

        if (!$isValid) {
            $responseData['reason'] = $reason;
        }

        return $responseData;
    }

    /**
     * @param \LC\Portal\OAuth\VpnAccessTokenInfo $accessTokenInfo
     *
     * @return array<string>
     */
    private function getPermissionList(VpnAccessTokenInfo $accessTokenInfo)
    {
        if (!$accessTokenInfo->getIsLocal()) {
            return [];
        }

        return $this->serverClient->getRequireArray('user_permission_list', ['user_id' => $accessTokenInfo->getUserId()]);
    }

    /**
     * @param \LC\Portal\OAuth\VpnAccessTokenInfo $accessTokenInfo
     *
     * @return \DateTime
     */
    private function getExpiresAt(VpnAccessTokenInfo $accessTokenInfo)
    {
        if (!$accessTokenInfo->getIsLocal()) {
            return date_add(clone $this->dateTime, $this->sessionExpiry);
        }

        return new DateTime($this->serverClient->getRequireString('user_session_expires_at', ['user_id' => $accessTokenInfo->getUserId()]));
    }
}
