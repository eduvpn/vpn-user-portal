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
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\ProfileConfig;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;
use Vpn\Portal\Validator;

class VpnApiThreeModule implements ServiceModuleInterface
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

    public function init(ServiceInterface $service): void
    {
        $service->get(
            '/v3/info',
            function (AccessToken $accessToken, Request $request): Response {
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
            function (AccessToken $accessToken, Request $request): Response {
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

                    $vpnProto = self::determineProto(
                        $profileConfig,
                        $request->optionalHeader('HTTP_ACCEPT'),
                        $publicKey,
                        $preferTcp
                    );

                    if ('wireguard' === $vpnProto && null === $publicKey) {
                        throw new HttpException('missing "public_key" parameter', 400);
                    }

                    // XXX if proto is wireguard, but it fails, do openvpn if supported
                    // XXX connection manager can also override perhaps?!
                    $clientConfig = $this->connectionManager->connect(
                        $this->serverInfo,
                        $accessToken->userId(),
                        $profileConfig->profileId(),
                        $vpnProto,
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
                } catch (ConnectionManagerException $e) {
                    throw new HttpException(sprintf('/connect failed: %s', $e->getMessage()), 500);
                }
            }
        );

        $service->post(
            '/v3/disconnect',
            function (AccessToken $accessToken, Request $request): Response {
                $this->connectionManager->disconnectByAuthKey($accessToken->authKey());

                return new Response(null, [], 204);
            }
        );
    }

    private static function determineProto(ProfileConfig $profileConfig, ?string $httpAccept, ?string $publicKey, bool $preferTcp): string
    {
        // figure out which protocols the client supports, if the server also
        // supports that one, go with it
        if (null !== $httpAccept) {
            $oSupport = false;
            $wSupport = false;
            $mimeTypeList = explode(',', $httpAccept);
            foreach ($mimeTypeList as $mimeType) {
                if ('application/x-openvpn-profile' === $mimeType) {
                    $oSupport = true;
                }
                if ('application/x-wireguard-profile' === $mimeType) {
                    $wSupport = true;
                }
            }

            if ($oSupport && false === $wSupport) {
                if ($profileConfig->oSupport()) {
                    return 'openvpn';
                }

                throw new HttpException(sprintf('profile "%s" does not support OpenVPN', $profileConfig->profileId()), 406);
            }

            if ($wSupport && false === $oSupport) {
                if ($profileConfig->wSupport()) {
                    return 'wireguard';
                }

                throw new HttpException(sprintf('profile "%s" does not support WireGuard', $profileConfig->profileId()), 406);
            }

            if (false === $oSupport && false === $wSupport) {
                throw new HttpException('client does not support OpenVPN or WireGuard', 406);
            }
        }

        // only supports OpenVPN
        if ($profileConfig->oSupport() && !$profileConfig->wSupport()) {
            return 'openvpn';
        }

        // only supports WireGuard
        if (!$profileConfig->oSupport() && $profileConfig->wSupport()) {
            return 'wireguard';
        }

        // Profile supports OpenVPN & WireGuard

        // VPN client prefers connecting over TCP
        if ($preferTcp) {
            // but this has only meaning if there are actually TCP ports to
            // connect to...
            if (0 !== \count($profileConfig->oExposedTcpPortList()) || 0 !== \count($profileConfig->oTcpPortList())) {
                return 'openvpn';
            }
        }

        // Profile prefers OpenVPN
        if ('openvpn' === $profileConfig->preferredProto()) {
            return 'openvpn';
        }

        // VPN client provides a WireGuard Public Key, server prefers WireGuard
        if (null !== $publicKey) {
            return 'wireguard';
        }

        // Server prefers WireGuard, but VPN client does not provide a
        // WireGuard Public Key, so use OpenVPN...
        return 'openvpn';
    }
}
