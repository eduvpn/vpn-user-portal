<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

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

class VpnApiThreeModule implements ApiServiceModuleInterface
{
    private Config $config;
    private Storage $storage;
    private TlsCrypt $tlsCrypt;
    private RandomInterface $random;
    private CaInterface $ca;
    private DateTimeImmutable $dateTime;

    public function __construct(Config $config, Storage $storage, TlsCrypt $tlsCrypt, RandomInterface $random, CaInterface $ca)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->tlsCrypt = $tlsCrypt;
        $this->random = $random;
        $this->ca = $ca;
        $this->dateTime = new DateTimeImmutable();
    }

    public function init(ApiService $service): void
    {
        $service->get(
            '/v3/info',
            function (VpnAccessToken $accessToken, Request $request): Response {
                $profileList = $this->profileList();
                // XXX really think about storing permissions in OAuth token!
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
                        'vpn_type' => $profileConfig->vpnType(),
                    ];
                }

                return new JsonResponse(
                    [
                        'info' => $userProfileList,
                    ]
                );
            }
        );

        $service->post(
            '/v3/connect',
            function (VpnAccessToken $accessToken, Request $request): Response {
                // XXX catch InputValidationException
                $requestedProfileId = InputValidation::profileId($request->requirePostParameter('profile_id'));
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
                    return new JsonResponse(['error' => 'profile not available or no permission'], [], 400);
                }

                $profileConfig = $profileList[$requestedProfileId];
                if ('openvpn' !== $profileConfig->vpnType()) {
                    return new JsonResponse(['error' => 'only OpenVPN supported for now by new API'], [], 400);
                }

                $commonName = $this->random->get(16);
                $certInfo = $this->ca->clientCert($commonName, $accessToken->accessToken()->authorizationExpiresAt());
                // XXX also store profile_id in DB
                $this->storage->addCertificate(
                    $accessToken->getUserId(),
                    $commonName,
                    $accessToken->accessToken()->clientId(),
                    new DateTimeImmutable(sprintf('@%d', $certInfo['valid_from'])),
                    new DateTimeImmutable(sprintf('@%d', $certInfo['valid_to'])),
                    $accessToken->accessToken()->clientId()
                );

                $this->storage->addUserLog(
                    $accessToken->getUserId(),
                    LoggerInterface::NOTICE,
                    sprintf('new certificate generated for "%s"', $accessToken->accessToken()->clientId()),
                    $this->dateTime
                );

                // get the CA & tls-crypt
                $serverInfo = [
                    'tls_crypt' => $this->tlsCrypt->get($requestedProfileId),
                    'ca' => $this->ca->caCert(),
                ];

                $clientConfig = ClientConfig::get($profileConfig, $serverInfo, $certInfo, ClientConfig::STRATEGY_RANDOM);
//                $clientConfig = str_replace("\n", "\r\n", $clientConfig);

                return new Response($clientConfig, ['Content-Type' => 'application/x-openvpn-profile']);
            }
        );

        $service->post(
            '/v3/disconnect',
            function (VpnAccessToken $accessToken, Request $request): Response {
                // we do nothing for now
                return new Response('', [], 204);
            }
        );
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
