<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use DateInterval;
use DateTime;
use DateTimeZone;
use fkooman\OAuth\Server\TokenInfo;
use SURFnet\VPN\Common\Http\ApiErrorResponse;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\Exception\InputValidationException;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\UserInfo;
use SURFnet\VPN\Common\HttpClient\Exception\ApiException;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\ProfileConfig;

class VpnApiModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \DateInterval */
    private $refreshTokenExpiry;

    /** @var bool */
    private $shuffleHosts = true;

    public function __construct(ServerClient $serverClient, DateInterval $refreshTokenExpiry)
    {
        $this->serverClient = $serverClient;
        $this->refreshTokenExpiry = $refreshTokenExpiry;
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
                $userGroups = $this->serverClient->getRequireArray('user_groups', ['user_id' => $userInfo->id()]);

                $userProfileList = [];
                foreach ($profileList as $profileId => $profileData) {
                    $profileConfig = new ProfileConfig($profileData);
                    if ($profileConfig->getItem('enableAcl')) {
                        // is the user member of the aclGroupList?
                        if (!VpnPortalModule::isMemberOrEntitled($profileConfig->getSection('aclGroupList')->toArray(), $userGroups, $userInfo->entitlementList())) {
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
        $service->get(
            '/user_info',
            /**
             * @return ApiResponse
             */
            function (Request $request, array $hookData) {
                /** @var \fkooman\OAuth\Server\TokenInfo */
                $tokenInfo = $hookData['auth'];
                $userInfo = $this->tokenInfoToUserInfo($tokenInfo);

                $hasYubiKeyId = $this->serverClient->getRequireBool('has_yubi_key_id', ['user_id' => $userInfo->id()]);
                $hasTotpSecret = $this->serverClient->getRequireBool('has_totp_secret', ['user_id' => $userInfo->id()]);
                $isDisabledUser = $this->serverClient->getRequireBool('is_disabled_user', ['user_id' => $userInfo->id()]);

                $twoFactorTypes = [];
                if ($hasYubiKeyId) {
                    $twoFactorTypes[] = 'yubi';
                }
                if ($hasTotpSecret) {
                    $twoFactorTypes[] = 'totp';
                }

                return new ApiResponse(
                    'user_info',
                    [
                        'user_id' => $userInfo->id(),
                        'two_factor_enrolled' => $hasYubiKeyId || $hasTotpSecret,
                        'two_factor_enrolled_with' => $twoFactorTypes,
                        'is_disabled' => $isDisabledUser,
                    ]
                );
            }
        );

        // API 2
        $service->post(
            '/create_keypair',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \fkooman\OAuth\Server\TokenInfo */
                $tokenInfo = $hookData['auth'];
                $userInfo = $this->tokenInfoToUserInfo($tokenInfo);

                try {
                    $displayName = InputValidation::displayName($request->getPostParameter('display_name'));
                    if (false === $certExpiresDays = self::toExpireDays(new DateTime(), $this->refreshTokenExpiry, $userInfo->authTime())) {
                        return new ApiErrorResponse('create_keypair', 'authTime in the future');
                    }
                    $clientCertificate = $this->getCertificate($tokenInfo, $displayName, $certExpiresDays);

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
             * @return \SURFnet\VPN\Common\Http\Response
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
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \fkooman\OAuth\Server\TokenInfo */
                $tokenInfo = $hookData['auth'];
                $userInfo = $this->tokenInfoToUserInfo($tokenInfo);

                try {
                    $requestedProfileId = InputValidation::profileId($request->getQueryParameter('profile_id'));

                    $profileList = $this->serverClient->getRequireArray('profile_list');
                    $userGroups = $this->serverClient->getRequireArray('user_groups', ['user_id' => $userInfo->id()]);

                    $availableProfiles = [];
                    foreach ($profileList as $profileId => $profileData) {
                        $profileConfig = new ProfileConfig($profileData);
                        if ($profileConfig->getItem('enableAcl')) {
                            // is the user member of the aclGroupList?
                            if (!VpnPortalModule::isMemberOrEntitled($profileConfig->getSection('aclGroupList')->toArray(), $userGroups, $userInfo->entitlementList())) {
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

        // API 1
        $service->post(
            '/create_config',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \fkooman\OAuth\Server\TokenInfo */
                $tokenInfo = $hookData['auth'];
                $userInfo = $this->tokenInfoToUserInfo($tokenInfo);

                try {
                    $displayName = InputValidation::displayName($request->getPostParameter('display_name'));
                    $profileId = InputValidation::profileId($request->getPostParameter('profile_id'));
                    if (false === $certExpiresDays = self::toExpireDays(new DateTime(), $this->refreshTokenExpiry, $userInfo->authTime())) {
                        return new ApiErrorResponse('create_config', 'authTime in the future');
                    }

                    return $this->getConfig($request->getServerName(), $profileId, $tokenInfo, $displayName, $certExpiresDays);
                } catch (InputValidationException $e) {
                    return new ApiErrorResponse('create_config', $e->getMessage());
                }
            }
        );

        $service->post(
            '/two_factor_enroll_yubi',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \fkooman\OAuth\Server\TokenInfo */
                $tokenInfo = $hookData['auth'];
                $userInfo = $this->tokenInfoToUserInfo($tokenInfo);

                try {
                    $hasYubiKeyId = $this->serverClient->getRequireBool('has_yubi_key_id', ['user_id' => $userInfo->id()]);
                    $hasTotpSecret = $this->serverClient->getRequireBool('has_totp_secret', ['user_id' => $userInfo->id()]);
                    if ($hasYubiKeyId || $hasTotpSecret) {
                        return new ApiErrorResponse('two_factor_enroll_yubi', 'user already enrolled');
                    }
                    $yubiKeyOtp = InputValidation::yubiKeyOtp($request->getPostParameter('yubi_key_otp'));
                    $this->serverClient->post('set_yubi_key_id', ['user_id' => $userInfo->id(), 'yubi_key_otp' => $yubiKeyOtp]);

                    return new ApiResponse('two_factor_enroll_yubi');
                } catch (ApiException $e) {
                    return new ApiErrorResponse('two_factor_enroll_yubi', $e->getMessage());
                } catch (InputValidationException $e) {
                    return new ApiErrorResponse('two_factor_enroll_yubi', $e->getMessage());
                }
            }
        );

        $service->post(
            '/two_factor_enroll_totp',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \fkooman\OAuth\Server\TokenInfo */
                $tokenInfo = $hookData['auth'];
                $userInfo = $this->tokenInfoToUserInfo($tokenInfo);

                $hasYubiKeyId = $this->serverClient->getRequireBool('has_yubi_key_id', ['user_id' => $userInfo->id()]);
                $hasTotpSecret = $this->serverClient->getRequireBool('has_totp_secret', ['user_id' => $userInfo->id()]);
                if ($hasYubiKeyId || $hasTotpSecret) {
                    return new ApiErrorResponse('two_factor_enroll_totp', 'user already enrolled');
                }

                try {
                    $totpKey = InputValidation::totpKey($request->getPostParameter('totp_key'));
                    $totpSecret = InputValidation::totpSecret($request->getPostParameter('totp_secret'));
                    $this->serverClient->post('set_totp_secret', ['user_id' => $userInfo->id(), 'totp_secret' => $totpSecret, 'totp_key' => $totpKey]);
                } catch (ApiException $e) {
                    return new ApiErrorResponse('two_factor_enroll_totp', $e->getMessage());
                } catch (InputValidationException $e) {
                    return new ApiErrorResponse('two_factor_enroll_totp', $e->getMessage());
                }

                return new ApiResponse('two_factor_enroll_totp');
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
     * @return false|int
     */
    public static function toExpireDays(DateTime $dateTime, DateInterval $dateInterval, DateTime $authTime)
    {
        if ($authTime >= $dateTime) {
            return false;
        }

        $authTimeDiff = date_diff(
            date_add(clone $authTime, $dateInterval),
            date_add(clone $dateTime, $dateInterval)
        );

        $dateIntervalDays = (int) $dateInterval->format('%d');
        $daysElapsed = (int) $authTimeDiff->format('%a');

        return $dateIntervalDays - $daysElapsed;
    }

    /**
     * @param string                          $serverName
     * @param string                          $profileId
     * @param \fkooman\OAuth\Server\TokenInfo $tokenInfo
     * @param string                          $displayName
     * @param int                             $certExpiresDays
     *
     * @return Response
     */
    private function getConfig($serverName, $profileId, TokenInfo $tokenInfo, $displayName, $certExpiresDays)
    {
        // obtain information about this profile to be able to construct
        // a client configuration file
        $profileList = $this->serverClient->getRequireArray('profile_list');
        $profileData = $profileList[$profileId];

        // create a certificate
        $clientCertificate = $this->getCertificate($tokenInfo, $displayName, $certExpiresDays);
        // get the CA & tls-auth
        $serverInfo = $this->serverClient->getRequireArray('server_info');

        $clientConfig = ClientConfig::get($profileData, $serverInfo, $clientCertificate, $this->shuffleHosts);
        $clientConfig = str_replace("\n", "\r\n", $clientConfig);

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setBody($clientConfig);

        return $response;
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
     * @param string                          $displayName
     * @param int                             $certExpiresDays
     *
     * @return array
     */
    private function getCertificate(TokenInfo $tokenInfo, $displayName, $certExpiresDays)
    {
        // create a certificate
        return $this->serverClient->postRequireArray(
            'add_client_certificate',
            [
                'user_id' => $this->tokenInfoToUserInfo($tokenInfo)->id(),
                'display_name' => $displayName,
                'client_id' => $tokenInfo->getClientId(),
                'cert_expires_days' => $certExpiresDays,
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
        } elseif ($clientCertificateInfo['user_is_disabled']) {
            // user account disabled by admin
            $isValid = false;
            $reason = 'user_disabled';
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
     * @return \SURFnet\VPN\Common\Http\UserInfo
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
        $authTimeStr = $this->serverClient->get('user_last_authenticated_at', ['user_id' => $userId]);
        $authTime = !\is_string($authTimeStr) ? new DateTime() : new DateTime($authTimeStr);

        return new UserInfo($userId, $entitlementList, $authTime);
    }
}
