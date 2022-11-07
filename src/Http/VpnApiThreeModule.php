<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use DateTimeImmutable;
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\Dt;
use Vpn\Portal\Exception\ConnectionManagerException;
use Vpn\Portal\Exception\ProtocolException;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\Protocol;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;
use Vpn\Portal\Validator;

class VpnApiThreeModule implements ApiServiceModuleInterface
{
    protected DateTimeImmutable $dateTime;
    private Config $config;
    private Storage $storage;
    private OAuthStorage $oauthStorage;
    private ServerInfo $serverInfo;
    private ConnectionManager $connectionManager;

    public function __construct(Config $config, Storage $storage, OAuthStorage $oauthStorage, ServerInfo $serverInfo, ConnectionManager $connectionManager)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->oauthStorage = $oauthStorage;
        $this->serverInfo = $serverInfo;
        $this->connectionManager = $connectionManager;
        $this->dateTime = Dt::get();
    }

    public function init(ApiServiceInterface $service): void
    {
        $service->get(
            '/v3/info',
            function (Request $request, ApiUserInfo $userInfo): Response {
                $profileConfigList = $this->config->profileConfigList();
                $userPermissions = $this->storage->userPermissionList($userInfo->userId());
                $userProfileList = [];
                foreach ($profileConfigList as $profileConfig) {
                    if (null !== $aclPermissionList = $profileConfig->aclPermissionList()) {
                        // is the user member of the aclPermissionList?
                        if (!VpnPortalModule::isMember($aclPermissionList, $userPermissions)) {
                            continue;
                        }
                    }

                    $userProfileList[] = [
                        'profile_id' => $profileConfig->profileId(),
                        'display_name' => $profileConfig->displayName(),
                        'vpn_proto_list' => $profileConfig->protoList(),
                        'default_gateway' => $profileConfig->defaultGateway(),
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
            function (Request $request, ApiUserInfo $userInfo): Response {
                try {
                    // make sure all client configurations / connections initiated
                    // by this client are removed / disconnected
                    $this->connectionManager->disconnectByAuthKey($userInfo->accessToken()->authKey());

                    $maxActiveApiConfigurations = $this->config->apiConfig()->maxActiveConfigurations();
                    if (0 === $maxActiveApiConfigurations) {
                        throw new HttpException('no API configuration downloads allowed', 403);
                    }
                    $activeApiConfigurations = $this->storage->activeApiConfigurations($userInfo->userId(), $this->dateTime);
                    if (\count($activeApiConfigurations) >= $maxActiveApiConfigurations) {
                        // we disconnect the client that connected the longest
                        // time ago, which is first one from the set in
                        // activeApiConfigurations
                        $this->connectionManager->disconnect(
                            $userInfo->userId(),
                            $activeApiConfigurations[0]['profile_id'],
                            $activeApiConfigurations[0]['connection_id']
                        );
                    }
                    $requestedProfileId = $request->requirePostParameter('profile_id', fn (string $s) => Validator::profileId($s));
                    $profileConfigList = $this->config->profileConfigList();
                    $userPermissions = $this->storage->userPermissionList($userInfo->userId());
                    $availableProfiles = [];
                    foreach ($profileConfigList as $profileConfig) {
                        if (null !== $aclPermissionList = $profileConfig->aclPermissionList()) {
                            // is the user member of the userPermissions?
                            if (!VpnPortalModule::isMember($aclPermissionList, $userPermissions)) {
                                continue;
                            }
                        }

                        $availableProfiles[] = $profileConfig->profileId();
                    }

                    if (!\in_array($requestedProfileId, $availableProfiles, true)) {
                        throw new HttpException('no such "profile_id"', 404);
                    }

                    $profileConfig = $this->config->profileConfig($requestedProfileId);
                    $publicKey = $request->optionalPostParameter('public_key', fn (string $s) => Validator::publicKey($s));

                    // still support "tcp_only" as an alias for "prefer_tcp",
                    // breaks when tcp_only=on and prefer_tcp=no, but we only
                    // want to support old clients (still using tcp_only) and
                    // new clients supporting prefer_tcp, and not a client
                    // where both are used...
                    $preferTcp = 'on' === $request->optionalPostParameter('tcp_only', fn (string $s) => Validator::onOrOff($s));
                    $preferTcp = $preferTcp || 'yes' === $request->optionalPostParameter('prefer_tcp', fn (string $s) => Validator::yesOrNo($s));

                    // XXX if public_key is missing when VPN client supports
                    // WireGuard that is a bug I guess, is the spec clear about
                    // this?!
                    $clientConfig = $this->connectionManager->connect(
                        $this->serverInfo,
                        $profileConfig,
                        $userInfo->userId(),
                        Protocol::parseMimeType($request->optionalHeader('HTTP_ACCEPT')),
                        $userInfo->accessToken()->clientId(),
                        $userInfo->accessToken()->authorizationExpiresAt(),
                        $preferTcp,
                        $publicKey,
                        $userInfo->accessToken()->authKey(),
                    );

                    return new Response(
                        $clientConfig->get(),
                        [
                            'Expires' => $userInfo->accessToken()->authorizationExpiresAt()->format(DateTimeImmutable::RFC7231),
                            'Content-Type' => $clientConfig->contentType(),
                        ]
                    );
                } catch (ProtocolException $e) {
                    throw new HttpException($e->getMessage(), 406);
                } catch (ConnectionManagerException $e) {
                    throw new HttpException(sprintf('/connect failed: %s', $e->getMessage()), 500);
                }
            }
        );

        $service->post(
            '/v3/disconnect',
            function (Request $request, ApiUserInfo $userInfo): Response {
                $this->connectionManager->disconnectByAuthKey($userInfo->accessToken()->authKey());

                // optionally remove the OAuth authorization if so requested by
                // the server configuration
                if ($this->config->apiConfig()->deleteAuthorizationOnDisconnect()) {
                    $this->oauthStorage->deleteAuthorization($userInfo->accessToken()->authKey());
                }

                return new Response(null, [], 204);
            }
        );
    }
}
