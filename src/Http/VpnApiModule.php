<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateInterval;
use DateTimeImmutable;
use LC\Portal\CA\CaInterface;
use LC\Portal\ClientConfig;
use LC\Portal\Config;
use LC\Portal\Http\Exception\InputValidationException;
use LC\Portal\LoggerInterface;
use LC\Portal\OAuth\VpnAccessToken;
use LC\Portal\ProfileConfig;
use LC\Portal\RandomInterface;
use LC\Portal\Storage;
use LC\Portal\TlsCrypt;

class VpnApiModule implements ApiServiceModuleInterface
{
    private Config $config;
    private Storage $storage;
    private DateInterval $sessionExpiry;
    private TlsCrypt $tlsCrypt;
    private RandomInterface $random;
    private CaInterface $ca;
    private DateTimeImmutable $dateTime;

    public function __construct(Config $config, Storage $storage, DateInterval $sessionExpiry, TlsCrypt $tlsCrypt, RandomInterface $random, CaInterface $ca)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->sessionExpiry = $sessionExpiry;
        $this->tlsCrypt = $tlsCrypt;
        $this->random = $random;
        $this->ca = $ca;
        $this->dateTime = new DateTimeImmutable();
    }

    public function init(ApiService $service): void
    {
        // API 1, 2
        $service->get(
            '/profile_list',
            function (VpnAccessToken $accessToken, Request $request): Response {
                $profileList = $this->profileList();
                $userPermissions = $this->getPermissionList($accessToken);
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
                        //'vpn_type' => $profileConfig->vpnType(),      // breaks Linux client
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
            function (VpnAccessToken $vpnAccessToken, Request $request): Response {
                return new ApiResponse(
                    'user_info',
                    [
                        'user_id' => $vpnAccessToken->getUserId(),
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
            function (VpnAccessToken $accessToken, Request $request): Response {
                try {
                    $clientCertificate = $this->getCertificate($accessToken);

                    return new ApiResponse(
                        'create_keypair',
                        [
                            'certificate' => $clientCertificate['cert'],
                            'private_key' => $clientCertificate['key'],
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
            function (VpnAccessToken $accessToken, Request $request): Response {
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
            function (VpnAccessToken $accessToken, Request $request): Response {
                try {
                    $requestedProfileId = InputValidation::profileId($request->requireQueryParameter('profile_id'));

                    $remoteStrategy = $request->optionalQueryParameter('remote_strategy');
                    if (null === $remoteStrategy) {
                        $remoteStrategy = ClientConfig::STRATEGY_RANDOM;
                    }
                    $remoteStrategy = (int) $remoteStrategy;

                    $profileList = $this->profileList();
                    $userPermissions = $this->getPermissionList($accessToken);

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
            function (VpnAccessToken $accessToken, Request $request): Response {
                return new ApiResponse(
                    'user_messages',
                    []
                );
            }
        );

        // NO LONGER USED
        $service->get(
            '/system_messages',
            function (VpnAccessToken $accessToken, Request $request): Response {
                return new ApiResponse(
                    'system_messages',
                    []
                );
            }
        );
    }

    private function getConfigOnly(string $profileId, int $remoteStrategy): Response
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

        $clientConfig = ClientConfig::get($profileConfig, $serverInfo, null, $remoteStrategy);
        $clientConfig = str_replace("\n", "\r\n", $clientConfig);

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setBody($clientConfig);

        return $response;
    }

    private function getCertificate(VpnAccessToken $vpnAccessToken): array
    {
        // create a certificate
        // generate a random string as the certificate's CN
        $commonName = $this->random->get(16);
        $certInfo = $this->ca->clientCert($commonName, $vpnAccessToken->accessToken()->authorizationExpiresAt());
        $this->storage->addCertificate(
            $vpnAccessToken->getUserId(),
            $commonName,
            $vpnAccessToken->accessToken()->clientId(),
            new DateTimeImmutable(sprintf('@%d', $certInfo['valid_from'])),
            new DateTimeImmutable(sprintf('@%d', $certInfo['valid_to'])),
            $vpnAccessToken->accessToken()->clientId()
        );

        $this->storage->addUserLog(
            $vpnAccessToken->getUserId(),
            LoggerInterface::NOTICE,
            sprintf('new certificate generated for "%s"', $vpnAccessToken->accessToken()->clientId()),
            $this->dateTime
        );

        // XXX better return type...this is not ideal
        return $certInfo;
    }

    /**
     * @param false|array $clientCertificateInfo
     *
     * @return array<string, bool|string>
     */
    private function validateCertificate($clientCertificateInfo): array
    {
        $reason = '';
        if (false === $clientCertificateInfo) {
            // certificate with this CN does not exist, was deleted by
            // user, or complete new installation of service with new
            // CA
            $isValid = false;
            $reason = 'certificate_missing';
        } elseif (new DateTimeImmutable($clientCertificateInfo['valid_from']) > $this->dateTime) {
            // certificate not yet valid
            $isValid = false;
            $reason = 'certificate_not_yet_valid';
        } elseif (new DateTimeImmutable($clientCertificateInfo['valid_to']) < $this->dateTime) {
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
    private function getPermissionList(VpnAccessToken $vpnAccessToken): array
    {
        if (!$vpnAccessToken->isLocal()) {
            return [];
        }

        return $this->storage->getPermissionList($vpnAccessToken->getUserId());
    }

    /**
     * XXX duplicate in AdminPortalModule|VpnPortalModule.
     *
     * @return array<string,\LC\Portal\ProfileConfig>
     */
    private function profileList(): array
    {
        $profileList = [];
        foreach ($this->config->requireArray('vpnProfiles') as $profileId => $profileData) {
            $profileConfig = new ProfileConfig(new Config($profileData));
            $profileList[$profileId] = $profileConfig;
        }

        return $profileList;
    }
}
