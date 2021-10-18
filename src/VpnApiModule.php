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
use LC\Common\Http\JsonResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\HttpClient\ServerClient;
use LC\Common\ProfileConfig;
use LC\Portal\Exception\ClientConfigException;
use LC\Portal\OAuth\VpnAccessTokenInfo;

class VpnApiModule implements ServiceModuleInterface
{
    /** @var \LC\Common\Config */
    private $config;

    /** @var \LC\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \DateInterval */
    private $sessionExpiry;

    /** @var \DateTime */
    private $dateTime;

    public function __construct(Config $config, ServerClient $serverClient, DateInterval $sessionExpiry)
    {
        $this->config = $config;
        $this->serverClient = $serverClient;
        $this->sessionExpiry = $sessionExpiry;
        $this->dateTime = new DateTime();
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        // API 3
        $service->get(
            '/v3/info',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\OAuth\VpnAccessTokenInfo */
                $accessTokenInfo = $hookData['auth'];

                $profileList = $this->serverClient->getRequireArray('profile_list');
                $userPermissions = $this->getPermissionList($accessTokenInfo);

                $responseData = [
                    'info' => [
                        'profile_list' => [],
                    ],
                ];

                $userProfileList = [];
                foreach ($profileList as $profileId => $profileData) {
                    $profileConfig = new ProfileConfig(new Config($profileData));
                    if ($profileConfig->hideProfile()) {
                        continue;
                    }
                    if ($profileConfig->enableAcl()) {
                        // is the user member of the aclPermissionList?
                        if (!VpnPortalModule::isMember($profileConfig->aclPermissionList(), $userPermissions)) {
                            continue;
                        }
                    }

                    $responseData['info']['profile_list'][] = [
                        'profile_id' => $profileId,
                        'default_gateway' => $profileConfig->defaultGateway(),
                        'display_name' => $profileConfig->displayName(),
                        'vpn_proto' => 'openvpn',
                    ];
                }

                return new JsonResponse($responseData, 200);
            }
        );

        $service->post(
            '/v3/connect',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\OAuth\VpnAccessTokenInfo */
                $accessTokenInfo = $hookData['auth'];
                try {
                    $requestedProfileId = InputValidation::profileId($request->requirePostParameter('profile_id'));
                    $remoteStrategy = ClientConfig::STRATEGY_RANDOM;
                    $profileList = $this->serverClient->getRequireArray('profile_list');
                    $userPermissions = $this->getPermissionList($accessTokenInfo);
                    $availableProfiles = [];
                    foreach ($profileList as $profileId => $profileData) {
                        $profileConfig = new ProfileConfig(new Config($profileData));
                        if ($profileConfig->hideProfile()) {
                            continue;
                        }
                        if ($profileConfig->enableAcl()) {
                            // is the user member of the userPermissions?
                            if (!VpnPortalModule::isMember($profileConfig->aclPermissionList(), $userPermissions)) {
                                continue;
                            }
                        }

                        $availableProfiles[] = $profileId;
                    }

                    if (!\in_array($requestedProfileId, $availableProfiles, true)) {
                        return new JsonResponse(['error' => 'profile not available'], 400);
                    }

                    $tcpOnly = 'on' === InputValidation::tcpOnly($request->optionalPostParameter('tcp_only'));
                    $vpnConfig = $this->getConfigOnly($requestedProfileId, $remoteStrategy, $tcpOnly);
                    $clientCertificate = $this->getCertificate($accessTokenInfo);
                    $vpnConfig .= "\n<cert>\n".$clientCertificate['certificate']."\n</cert>\n<key>\n".$clientCertificate['private_key']."\n</key>";
                    $response = new Response(200, 'application/x-openvpn-profile');
                    $response->addHeader('Expires', $this->getExpiresAt($accessTokenInfo)->format('D, d M Y H:i:s \G\M\T'));
                    $response->setBody($vpnConfig);

                    return $response;
                } catch (InputValidationException $e) {
                    return new JsonResponse(['error' => $e->getMessage()], 400);
                } catch (ClientConfigException $e) {
                    return new JsonResponse(['error' => $e->getMessage()], 406);
                }
            }
        );

        $service->post(
            '/v3/disconnect',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                return new Response(204);
            }
        );

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
                    $profileConfig = new ProfileConfig(new Config($profileData));
                    if ($profileConfig->hideProfile()) {
                        continue;
                    }
                    if ($profileConfig->enableAcl()) {
                        // is the user member of the aclPermissionList?
                        if (!VpnPortalModule::isMember($profileConfig->aclPermissionList(), $userPermissions)) {
                            continue;
                        }
                    }

                    $userProfileList[] = [
                        'profile_id' => $profileId,
                        'display_name' => $profileConfig->displayName(),
                        // 2FA is now decided by vpn-user-portal setting, so
                        // we "lie" here to the client
                        'two_factor' => false,
                        'default_gateway' => $profileConfig->defaultGateway(),
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
                $commonName = InputValidation::commonName($request->requireQueryParameter('common_name'));
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
                    $requestedProfileId = InputValidation::profileId($request->requireQueryParameter('profile_id'));

                    $remoteStrategy = $request->optionalQueryParameter('remote_strategy');
                    if (null === $remoteStrategy) {
                        $remoteStrategy = ClientConfig::STRATEGY_RANDOM;
                    }
                    $remoteStrategy = (int) $remoteStrategy;

                    $profileList = $this->serverClient->getRequireArray('profile_list');
                    $userPermissions = $this->getPermissionList($accessTokenInfo);

                    $availableProfiles = [];
                    foreach ($profileList as $profileId => $profileData) {
                        $profileConfig = new ProfileConfig(new Config($profileData));
                        if ($profileConfig->hideProfile()) {
                            continue;
                        }
                        if ($profileConfig->enableAcl()) {
                            // is the user member of the userPermissions?
                            if (!VpnPortalModule::isMember($profileConfig->aclPermissionList(), $userPermissions)) {
                                continue;
                            }
                        }

                        $availableProfiles[] = $profileId;
                    }

                    if (!\in_array($requestedProfileId, $availableProfiles, true)) {
                        return new ApiErrorResponse('profile_config', 'profile not available or no permission');
                    }

                    // APIv2 has no support for "tcpOnly" OpenVPN profiles, so
                    // always false...
                    $vpnConfig = $this->getConfigOnly($requestedProfileId, $remoteStrategy, false);
                    $response = new Response(200, 'application/x-openvpn-profile');
                    $response->setBody(str_replace("\n", "\r\n", $vpnConfig));

                    return $response;
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
     * @param int    $remoteStrategy
     * @param bool   $tcpOnly
     *
     * @return string
     */
    private function getConfigOnly($profileId, $remoteStrategy, $tcpOnly)
    {
        // obtain information about this profile to be able to construct
        // a client configuration file
        $profileList = $this->serverClient->getRequireArray('profile_list');
        $profileConfig = new ProfileConfig(new Config($profileList[$profileId]));

        // get the CA & tls-auth
        $serverInfo = $this->serverClient->getRequireArray('server_info', ['profile_id' => $profileId]);

        return ClientConfig::get($profileConfig, $serverInfo, [], $remoteStrategy, $tcpOnly);
    }

    /**
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
