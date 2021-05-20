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
use DateTimeZone;
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
use LC\Portal\WireGuard\Wg;

class VpnApiThreeModule implements ApiServiceModuleInterface
{
    private Config $config;
    private Storage $storage;
    private TlsCrypt $tlsCrypt;
    private RandomInterface $random;
    private CaInterface $ca;
    private Wg $wg;
    private DateTimeImmutable $dateTime;

    public function __construct(Config $config, Storage $storage, TlsCrypt $tlsCrypt, RandomInterface $random, CaInterface $ca, Wg $wg)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->tlsCrypt = $tlsCrypt;
        $this->random = $random;
        $this->ca = $ca;
        $this->wg = $wg;
        $this->dateTime = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function init(ApiService $service): void
    {
        $service->get(
            '/v3/info',
            function (VpnAccessToken $accessToken, Request $request): Response {
                $profileConfigList = $this->config->profileConfigList();
                // XXX really think about storing permissions in OAuth token!
                $userPermissions = $this->getPermissionList($accessToken);
                $userProfileList = [];
                foreach ($profileConfigList as $profileConfig) {
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
                        'profile_id' => $profileConfig->profileId(),
                        'display_name' => $profileConfig->displayName(),
                    ];
                }

                return new JsonResponse(
                    [
                        'info' => [
                            'profile_list' => $userProfileList,
                        ],
                    ]
                );
            }
        );

        $service->post(
            '/v3/connect',
            function (VpnAccessToken $accessToken, Request $request): Response {
                // XXX catch InputValidationException
                $requestedProfileId = InputValidation::profileId($request->requirePostParameter('profile_id'));
                $profileConfigList = $this->config->profileConfigList();
                $userPermissions = $this->getPermissionList($accessToken);
                $availableProfiles = [];
                foreach ($profileConfigList as $profileConfig) {
                    if ($profileConfig->hideProfile()) {
                        continue;
                    }
                    if ($profileConfig->enableAcl()) {
                        // is the user member of the userPermissions?
                        if (!VpnPortalModule::isMember($profileConfig->aclPermissionList(), $userPermissions)) {
                            continue;
                        }
                    }

                    $availableProfiles[] = $profileConfig->profileId();
                }

                if (!\in_array($requestedProfileId, $availableProfiles, true)) {
                    return new JsonResponse(['error' => 'profile not available or no permission'], [], 400);
                }

                $profileConfig = $this->config->profileConfig($requestedProfileId);

                switch ($profileConfig->vpnType()) {
                    case 'openvpn':
                        return $this->getOpenVpnConfigResponse($profileConfig, $accessToken);
                    case 'wireguard':
                        $wgConfig = $this->wg->getConfig(
                            $profileConfig,
                            $accessToken->getUserId(),
                            $accessToken->accessToken()->clientId(),
                            $accessToken->accessToken()
                        );

                        return new Response(
                            (string) $wgConfig,
                            [
                                'Expires' => $accessToken->accessToken()->authorizationExpiresAt()->format(DateTimeImmutable::RFC7231),
                                'Content-Type' => 'application/x-wireguard-profile',
                            ]
                        );
                    default:
                        return new JsonResponse(['error' => 'invalid vpn_type'], [], 500);
                }
            }
        );
    }

    /**
     * XXX want only 1 code path both for portal and for API.
     */
    private function getOpenVpnConfigResponse(ProfileConfig $profileConfig, VpnAccessToken $accessToken): Response
    {
        $commonName = $this->random->get(16);
        $certInfo = $this->ca->clientCert($commonName, $profileConfig->profileId(), $accessToken->accessToken()->authorizationExpiresAt());
        $this->storage->addCertificate(
            $accessToken->getUserId(),
            $profileConfig->profileId(),
            $commonName,
            $accessToken->accessToken()->clientId(),
            $certInfo->validFrom(),
            $certInfo->validTo(),
            $accessToken->accessToken()
        );

        $this->storage->addUserLog(
            $accessToken->getUserId(),
            LoggerInterface::NOTICE,
            sprintf('new certificate generated for "%s"', $accessToken->accessToken()->clientId()),
            $this->dateTime
        );

        $clientConfig = ClientConfig::get(
            $profileConfig,
            $this->ca->caCert(),
            $this->tlsCrypt,
            $certInfo,
            ClientConfig::STRATEGY_RANDOM
        );

        return new Response(
            $clientConfig,
            [
                'Expires' => $accessToken->accessToken()->authorizationExpiresAt()->format(DateTimeImmutable::RFC7231),
                'Content-Type' => 'application/x-openvpn-profile',
            ]
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
}
