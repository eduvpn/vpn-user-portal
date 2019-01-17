<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use DateInterval;
use DateTime;
use DateTimeZone;
use fkooman\OAuth\Server\TokenInfo;
use LetsConnect\Common\Config;
use LetsConnect\Common\Http\ApiErrorResponse;
use LetsConnect\Common\Http\ApiResponse;
use LetsConnect\Common\Http\Exception\InputValidationException;
use LetsConnect\Common\Http\InputValidation;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Response;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\Http\ServiceModuleInterface;
use LetsConnect\Common\Http\UserInfo;
use LetsConnect\Common\HttpClient\ServerClient;
use LetsConnect\Common\ProfileConfig;

class VpnApiModule implements ServiceModuleInterface
{
    /** @var \LetsConnect\Common\Config */
    private $config;

    /** @var \LetsConnect\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \DateInterval */
    private $sessionExpiry;

    /** @var bool */
    private $shuffleHosts = true;

    /**
     * @param \LetsConnect\Common\Config                  $config
     * @param \LetsConnect\Common\HttpClient\ServerClient $serverClient
     * @param \DateInterval                               $sessionExpiry
     */
    public function __construct(Config $config, ServerClient $serverClient, DateInterval $sessionExpiry)
    {
        $this->config = $config;
        $this->serverClient = $serverClient;
        $this->sessionExpiry = $sessionExpiry;
    }

    /**
     * @param bool $shuffleHosts
     *
     * @return void
     */
    public function setShuffleHosts($shuffleHosts)
    {
        $this->shuffleHosts = (bool) $shuffleHosts;
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
                /** @var \fkooman\OAuth\Server\TokenInfo */
                $tokenInfo = $hookData['auth'];
                $userInfo = $this->tokenInfoToUserInfo($tokenInfo);

                $profileList = $this->serverClient->getRequireArray('profile_list');
                $userGroups = $this->serverClient->getRequireArray('user_entitlement_list', ['user_id' => $userInfo->id()]);

                $userProfileList = [];
                foreach ($profileList as $profileId => $profileData) {
                    $profileConfig = new ProfileConfig($profileData);
                    if ($profileConfig->getItem('enableAcl')) {
                        // is the user member of the aclGroupList?
                        if (!VpnPortalModule::isMember($profileConfig->getSection('aclGroupList')->toArray(), $userGroups)) {
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
                /** @var \fkooman\OAuth\Server\TokenInfo */
                $tokenInfo = $hookData['auth'];
                $userInfo = $this->tokenInfoToUserInfo($tokenInfo);

                return new ApiResponse(
                    'user_info',
                    [
                        'user_id' => $userInfo->id(),
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
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \fkooman\OAuth\Server\TokenInfo */
                $tokenInfo = $hookData['auth'];
                $userInfo = $this->tokenInfoToUserInfo($tokenInfo);

                try {
                    $expiresAt = date_add(clone $userInfo->authTime(), $this->sessionExpiry);
                    $clientCertificate = $this->getCertificate($tokenInfo, $expiresAt);

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
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \fkooman\OAuth\Server\TokenInfo */
                $tokenInfo = $hookData['auth'];
                $userInfo = $this->tokenInfoToUserInfo($tokenInfo);

                $commonName = InputValidation::commonName($request->getQueryParameter('common_name'));
                $clientCertificateInfo = $this->serverClient->getRequireArrayOrFalse('client_certificate_info', ['common_name' => $commonName]);
                $responseData = self::validateCertificate($clientCertificateInfo);

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
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \fkooman\OAuth\Server\TokenInfo */
                $tokenInfo = $hookData['auth'];
                $userInfo = $this->tokenInfoToUserInfo($tokenInfo);

                try {
                    $requestedProfileId = InputValidation::profileId($request->getQueryParameter('profile_id'));

                    $profileList = $this->serverClient->getRequireArray('profile_list');
                    $userGroups = $this->serverClient->getRequireArray('user_entitlement_list', ['user_id' => $userInfo->id()]);

                    $availableProfiles = [];
                    foreach ($profileList as $profileId => $profileData) {
                        $profileConfig = new ProfileConfig($profileData);
                        if ($profileConfig->getItem('enableAcl')) {
                            // is the user member of the aclGroupList?
                            if (!VpnPortalModule::isMember($profileConfig->getSection('aclGroupList')->toArray(), $userGroups)) {
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
                /** @var \fkooman\OAuth\Server\TokenInfo */
                $userInfo = $hookData['auth'];

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

        $clientConfig = ClientConfig::get($profileData, $serverInfo, [], $this->shuffleHosts);
        $clientConfig = str_replace("\n", "\r\n", $clientConfig);

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setBody($clientConfig);

        return $response;
    }

    /**
     * @param \fkooman\OAuth\Server\TokenInfo $tokenInfo
     * @param \DateTime                       $expiresAt
     *
     * @return array
     */
    private function getCertificate(TokenInfo $tokenInfo, DateTime $expiresAt)
    {
        // create a certificate
        return $this->serverClient->postRequireArray(
            'add_client_certificate',
            [
                'user_id' => $this->tokenInfoToUserInfo($tokenInfo)->id(),
                // we won't show the Certificate entry anyway on the
                // "Certificates" page for certificates downloaded through the
                // API
                'display_name' => $tokenInfo->getClientId(),
                'client_id' => $tokenInfo->getClientId(),
                'expires_at' => $expiresAt->format(DateTime::ATOM),
            ]
        );
    }

    /**
     * @param false|array $clientCertificateInfo
     *
     * @return array<string, bool|string>
     */
    private static function validateCertificate($clientCertificateInfo)
    {
        $reason = '';
        if (false === $clientCertificateInfo) {
            // certificate with this CN does not exist, was deleted by
            // user, or complete new installation of service with new
            // CA
            $isValid = false;
            $reason = 'certificate_missing';
        } elseif (new DateTime($clientCertificateInfo['valid_from']) > new DateTime()) {
            // certificate not yet valid
            $isValid = false;
            $reason = 'certificate_not_yet_valid';
        } elseif (new DateTime($clientCertificateInfo['valid_to']) < new DateTime()) {
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
     * @param \fkooman\OAuth\Server\TokenInfo $tokenInfo
     *
     * @return \LetsConnect\Common\Http\UserInfo
     */
    private function tokenInfoToUserInfo(TokenInfo $tokenInfo)
    {
        $keyId = $tokenInfo->getKeyId();
        if ('local' !== $keyId) {
            // use the key ID as part of the user_id to indicate this is a "foreign" user
            return new UserInfo(
                sprintf(
                    '%s_%s',
                    preg_replace('/__*/', '_', preg_replace('/[^A-Za-z0-9.]/', '_', $keyId)),
                    $tokenInfo->getUserId()
                ),
                // no entitlements for remote users
                [],
                // we can't determine the time the user last authenticated
                // through the remote instance, so we just use current time for
                // now until we propagate this through the OAuth token
                new DateTime()
            );
        }

        $userId = $tokenInfo->getUserId();
        $entitlementList = $this->serverClient->getRequireArray('user_entitlement_list', ['user_id' => $userId]);
        // the response is possibly NULL in case the user didn't authenticate
        // since the token was issued and the database changed recording the
        // user_last_authenticate_at... we still want to accept those tokens
        // as well, but ideally we no longer accept this at some point!
        // XXX save this for "mass revocation of tokens" planned some time in
        // the future
        $authTimeStr = $this->serverClient->get('user_last_authenticated_at', ['user_id' => $userId]);
        $authTime = !\is_string($authTimeStr) ? new DateTime() : new DateTime($authTimeStr);

        return new UserInfo($userId, $entitlementList, $authTime);
    }
}
