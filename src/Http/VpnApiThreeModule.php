<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use DateTimeImmutable;
use fkooman\OAuth\Server\AccessToken;
use Vpn\Portal\Config;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\Dt;
use Vpn\Portal\Exception\ConnectionManagerException;
use Vpn\Portal\Exception\ProtocolException;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;
use Vpn\Portal\Validator;

class VpnApiThreeModule implements ApiServiceModuleInterface
{
    protected DateTimeImmutable $dateTime;
    private Config $config;
    private Storage $storage;
    private ServerInfo $serverInfo;
    private ConnectionManager $connectionManager;

    public function __construct(Config $config, Storage $storage, ServerInfo $serverInfo, ConnectionManager $connectionManager)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->serverInfo = $serverInfo;
        $this->connectionManager = $connectionManager;
        $this->dateTime = Dt::get();
    }

    public function init(ApiServiceInterface $service): void
    {
        $service->get(
            '/v3/info',
            function (Request $request, AccessToken $accessToken): Response {
                $profileConfigList = $this->config->profileConfigList();
                $userPermissions = $this->storage->userPermissionList($accessToken->userId());
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
            function (Request $request, AccessToken $accessToken): Response {
                try {
                    // make sure all client configurations / connections initiated
                    // by this client are removed / disconnected
                    $this->connectionManager->disconnectByAuthKey($accessToken->authKey());

                    $maxActiveApiConfigurations = $this->config->apiConfig()->maxActiveConfigurations();
                    if (0 === $maxActiveApiConfigurations) {
                        throw new HttpException('no API configuration downloads allowed', 403);
                    }
                    $activeApiConfigurations = $this->storage->activeApiConfigurations($accessToken->userId(), $this->dateTime);
                    if (\count($activeApiConfigurations) >= $maxActiveApiConfigurations) {
                        // we disconnect the client that connected the longest
                        // time ago, which is first one from the set in
                        // activeApiConfigurations
                        $this->connectionManager->disconnect(
                            $accessToken->userId(),
                            $activeApiConfigurations[0]['profile_id'],
                            $activeApiConfigurations[0]['connection_id']
                        );
                    }
                    $requestedProfileId = $request->requirePostParameter('profile_id', fn (string $s) => Validator::profileId($s));
                    $profileConfigList = $this->config->profileConfigList();
                    $userPermissions = $this->storage->userPermissionList($accessToken->userId());
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
                        $accessToken->userId(),
                        self::parseMimeType($request->optionalHeader('HTTP_ACCEPT')),
                        $accessToken->clientId(),
                        $accessToken->authorizationExpiresAt(),
                        $preferTcp,
                        $publicKey,
                        $accessToken->authKey(),
                    );

                    return new Response(
                        $clientConfig->get(),
                        [
                            'Expires' => $accessToken->authorizationExpiresAt()->format(DateTimeImmutable::RFC7231),
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
            function (Request $request, AccessToken $accessToken): Response {
                $this->connectionManager->disconnectByAuthKey($accessToken->authKey());

                return new Response(null, [], 204);
            }
        );
    }

    /**
     * We only take the Accept header serious if we detect at least one
     * mime-type we recognize, otherwise we assume it is garbage and consider
     * it as "not sent".
     *
     * @return array{wireguard:bool,openvpn:bool}
     */
    private static function parseMimeType(?string $httpAccept): array
    {
        if (null === $httpAccept) {
            return ['wireguard' => true, 'openvpn' => true];
        }

        $oSupport = false;
        $wSupport = false;
        $takeSerious = false;

        $mimeTypeList = explode(',', $httpAccept);
        foreach ($mimeTypeList as $mimeType) {
            // XXX do we need to trim mimeType?
            if ('application/x-openvpn-profile' === $mimeType) {
                $oSupport = true;
                $takeSerious = true;
            }
            if ('application/x-wireguard-profile' === $mimeType) {
                $wSupport = true;
                $takeSerious = true;
            }
        }
        if (false === $takeSerious) {
            return ['wireguard' => true, 'openvpn' => true];
        }

        return ['wireguard' => $wSupport, 'openvpn' => $oSupport];
    }
}
