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
use fkooman\OAuth\Server\AccessToken;
use LC\Portal\Config;
use LC\Portal\Dt;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\LoggerInterface;
use LC\Portal\OpenVpn\CA\CaInterface;
use LC\Portal\OpenVpn\ClientConfig;
use LC\Portal\OpenVpn\Exception\ClientConfigException;
use LC\Portal\OpenVpn\TlsCrypt;
use LC\Portal\ProfileConfig;
use LC\Portal\RandomInterface;
use LC\Portal\Storage;
use LC\Portal\Validator;
use LC\Portal\WireGuard\Wg;

class VpnApiThreeModule implements ServiceModuleInterface
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
        $this->dateTime = Dt::get();
    }

    public function init(ServiceInterface $service): void
    {
        $service->get(
            '/v3/info',
            function (AccessToken $accessToken, Request $request): Response {
                $profileConfigList = $this->config->profileConfigList();
                // XXX really think about storing permissions in OAuth token!
                $userPermissions = $this->storage->getPermissionList($accessToken->userId());
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
                        'vpn_proto' => $profileConfig->vpnProto(),
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
            function (AccessToken $accessToken, Request $request): Response {
                // XXX catch InputValidationException
                $requestedProfileId = $request->requirePostParameter('profile_id', fn (string $s) => Validator::re($s, Validator::REGEXP_PROFILE_ID));
                $profileConfigList = $this->config->profileConfigList();
                $userPermissions = $this->storage->getPermissionList($accessToken->userId());
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
                    return new JsonResponse(['error' => 'profile not available'], [], 400);
                }

                $profileConfig = $this->config->profileConfig($requestedProfileId);

                // XXX delete all OpenVPN and WireGuard active configs with this auth_id

                switch ($profileConfig->vpnProto()) {
                    case 'openvpn':
                        $tcpOnly = 'on' === $request->optionalPostParameter('tcp_only', fn (string $s) => \in_array($s, ['on', 'off'], true));

                        return $this->getOpenVpnConfigResponse($profileConfig, $accessToken, $tcpOnly);

                    case 'wireguard':
                        $wgConfig = $this->wg->addPeer(
                            $profileConfig,
                            $accessToken->userId(),
                            $accessToken->clientId(),
                            $accessToken->authorizationExpiresAt(),
                            $accessToken,
                            $request->requirePostParameter('public_key', fn (string $s) => Validator::re($s, Validator::REGEXP_PUBLIC_KEY))
                        );

                        return new Response(
                            (string) $wgConfig,
                            [
                                'Expires' => $accessToken->authorizationExpiresAt()->format(DateTimeImmutable::RFC7231),
                                'Content-Type' => 'application/x-wireguard-profile',
                            ]
                        );

                    default:
                        // XXX why not exception?
                        return new JsonResponse(['error' => 'invalid vpn_type'], [], 500);
                }
            }
        );

        $service->post(
            '/v3/disconnect',
            function (AccessToken $accessToken, Request $request): Response {
                // XXX duplicate from connect
                // XXX catch InputValidationException
                // XXX why do we need profile_id again?

                $requestedProfileId = $request->requirePostParameter('profile_id', fn (string $s) => Validator::re($s, Validator::REGEXP_PROFILE_ID));
                $profileConfigList = $this->config->profileConfigList();
                $userPermissions = $this->storage->getPermissionList($accessToken->userId());
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
                    return new JsonResponse(['error' => 'profile not available'], [], 400);
                }

                $profileConfig = $this->config->profileConfig($requestedProfileId);

                if ('openvpn' === $profileConfig->vpnProto()) {
                    // OpenVPN
                    $this->storage->deleteCertificatesWithAuthKey($accessToken->authKey());
                    // XXX do we also need to disconnect the client?

                    return new Response(null, [], 204);
                }

                // WireGuard
                $userId = $accessToken->userId();
                $authKey = $accessToken->authKey();
                // XXX move this to Wg class
                foreach ($this->storage->wgGetPeers($userId) as $wgPeer) {
                    if ($requestedProfileId !== $wgPeer['profile_id']) {
                        continue;
                    }
                    if ($authKey !== $wgPeer['auth_key']) {
                        continue;
                    }

                    $this->wg->removePeer(
                        $profileConfig,
                        $userId,
                        $wgPeer['public_key']
                    );
                }

                return new Response(null, [], 204);
            }
        );
    }

    /**
     * XXX want only 1 code path both for portal and for API.
     */
    private function getOpenVpnConfigResponse(ProfileConfig $profileConfig, AccessToken $accessToken, bool $tcpOnly): Response
    {
        try {
            $commonName = $this->random->get(16);
            $certInfo = $this->ca->clientCert($commonName, $profileConfig->profileId(), $accessToken->authorizationExpiresAt());
            $this->storage->addCertificate(
                $accessToken->userId(),
                $profileConfig->profileId(),
                $commonName,
                $accessToken->clientId(),
                $accessToken->authorizationExpiresAt(),
                $accessToken
            );

            $this->storage->addUserLog(
                $accessToken->userId(),
                LoggerInterface::NOTICE,
                sprintf('new certificate generated for "%s"', $accessToken->clientId()),
                $this->dateTime
            );

            $clientConfig = ClientConfig::get(
                $profileConfig,
                $this->ca->caCert(),
                $this->tlsCrypt,
                $certInfo,
                ClientConfig::STRATEGY_RANDOM,
                $tcpOnly
            );

            return new Response(
                $clientConfig,
                [
                    'Expires' => $accessToken->authorizationExpiresAt()->format(DateTimeImmutable::RFC7231),
                    'Content-Type' => 'application/x-openvpn-profile',
                ]
            );
        } catch (ClientConfigException $e) {
            throw new HttpException($e->getMessage(), 406);
        }
    }
}
