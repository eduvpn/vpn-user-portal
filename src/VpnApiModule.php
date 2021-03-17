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
use LC\Common\Config;
use LC\Common\Http\ApiErrorResponse;
use LC\Common\Http\ApiResponse;
use LC\Common\Http\Exception\InputValidationException;
use LC\Common\Http\InputValidation;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\ProfileConfig;
use LC\Common\RandomInterface;
use LC\Portal\CA\CaInterface;
use LC\Portal\OAuth\VpnAccessTokenInfo;

class VpnApiModule implements ServiceModuleInterface
{
    /** @var \LC\Common\Config */
    private $config;

    /** @var Storage */
    private $storage;

    /** @var \DateInterval */
    private $sessionExpiry;

    /** @var TlsCrypt */
    private $tlsCrypt;

    /** @var \LC\Common\RandomInterface */
    private $random;

    /** @var CA\CaInterface */
    private $ca;

    /** @var \DateTime */
    private $dateTime;

    public function __construct(Config $config, Storage $storage, DateInterval $sessionExpiry, TlsCrypt $tlsCrypt, RandomInterface $random, CaInterface $ca)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->sessionExpiry = $sessionExpiry;
        $this->tlsCrypt = $tlsCrypt;
        $this->random = $random;
        $this->ca = $ca;
        $this->dateTime = new DateTime();
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

                $profileList = $this->profileList();
                $userPermissions = $this->getPermissionList($accessTokenInfo);
                $userProfileList = [];
                foreach ($profileList as $profileId => $profileConfig) {
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
                $clientCertificateInfo = $this->storage->getUserCertificateInfo($commonName);
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

                    $profileList = $this->profileList();
                    $userPermissions = $this->getPermissionList($accessTokenInfo);

                    $availableProfiles = [];
                    foreach ($profileList as $profileId => $profileConfig) {
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

                    return $this->getConfigOnly($requestedProfileId, $remoteStrategy);
                } catch (InputValidationException $e) {
                    return new ApiErrorResponse('profile_config', $e->getMessage());
                }
            }
        );

        // NO LONGER USED
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

        // NO LONGER USED
        $service->get(
            '/system_messages',
            /**
             * @return ApiResponse
             */
            function (Request $request, array $hookData) {
                return new ApiResponse(
                    'system_messages',
                    []
                );
            }
        );
    }

    /**
     * @param string $profileId
     * @param int    $remoteStrategy
     *
     * @return Response
     */
    private function getConfigOnly($profileId, $remoteStrategy)
    {
        // obtain information about this profile to be able to construct
        // a client configuration file
        $profileList = $this->profileList();
        // XXX we should really consider making an object for profileList!
        $profileConfig = $profileList[$profileId];

        // get the CA & tls-crypt
        $serverInfo = [
            'tls_crypt' => $this->tlsCrypt->get($profileId),
            'ca' => $this->ca->caCert(),
        ];

        $clientConfig = ClientConfig::get($profileConfig, $serverInfo, [], $remoteStrategy);
        $clientConfig = str_replace("\n", "\r\n", $clientConfig);

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setBody($clientConfig);

        return $response;
    }

    /**
     * @return array
     */
    private function getCertificate(VpnAccessTokenInfo $accessTokenInfo)
    {
        // create a certificate
        // generate a random string as the certificate's CN
        $commonName = $this->random->get(16);
        $certInfo = $this->ca->clientCert($commonName, $this->getExpiresAt($accessTokenInfo));
        $this->storage->addCertificate(
            $accessTokenInfo->getUserId(),
            $commonName,
            $accessTokenInfo->getClientId(),
            new DateTime(sprintf('@%d', $certInfo['valid_from'])),
            new DateTime(sprintf('@%d', $certInfo['valid_to'])),
            $accessTokenInfo->getClientId()
        );

        $this->storage->addUserMessage(
            $accessTokenInfo->getUserId(),
            'notification',
            sprintf('new certificate "%s" generated by user', $accessTokenInfo->getClientId())
        );

        // XXX better return type...this is not ideal
        return $certInfo;
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

        return $this->storage->getPermissionList($accessTokenInfo->getUserId());
    }

    /**
     * @return \DateTime
     */
    private function getExpiresAt(VpnAccessTokenInfo $accessTokenInfo)
    {
        if (!$accessTokenInfo->getIsLocal()) {
            return date_add(clone $this->dateTime, $this->sessionExpiry);
        }

        return new DateTime($this->storage->getSessionExpiresAt($accessTokenInfo->getUserId()));
    }

    /**
     * XXX duplicate in AdminPortalModule|VpnPortalModule.
     *
     * @return array<string,\LC\Common\ProfileConfig>
     */
    private function profileList()
    {
        $profileList = [];
        foreach ($this->config->requireArray('vpnProfiles') as $profileId => $profileData) {
            $profileConfig = new ProfileConfig(new Config($profileData));
            $profileList[$profileId] = $profileConfig;
        }

        return $profileList;
    }
}
