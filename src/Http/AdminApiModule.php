<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use DateTimeImmutable;
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\Dt;
use Vpn\Portal\Exception\ConnectionManagerException;
use Vpn\Portal\Exception\ProtocolException;
use Vpn\Portal\Expiry;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\Protocol;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;
use Vpn\Portal\Validator;
use Vpn\Portal\WireGuard\ClientConfig as WireGuardClientConfig;
use Vpn\Portal\WireGuard\Key;

class AdminApiModule implements ServiceModuleInterface
{
    protected DateTimeImmutable $dateTime;
    private Config $config;
    private Storage $storage;
    private ServerInfo $serverInfo;
    private ConnectionManager $connectionManager;
    private Expiry $sessionExpiry;

    public function __construct(Config $config, Storage $storage, ServerInfo $serverInfo, ConnectionManager $connectionManager, Expiry $sessionExpiry)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->serverInfo = $serverInfo;
        $this->connectionManager = $connectionManager;
        $this->sessionExpiry = $sessionExpiry;
        $this->dateTime = Dt::get();
    }

    public function init(ServiceInterface $service): void
    {
        $service->post(
            '/v1/create',
            function (Request $request, UserInfo $userInfo): Response {
                try {
                    if (!$this->storage->userExists($userInfo->userId())) {
                        $this->storage->userAdd($userInfo, $this->dateTime);
                    }

                    $requestedProfileId = $request->requirePostParameter('profile_id', fn (string $s) => Validator::profileId($s));
                    $profileConfigList = $this->config->profileConfigList();
                    $availableProfiles = [];
                    foreach ($profileConfigList as $profileConfig) {
                        $availableProfiles[] = $profileConfig->profileId();
                    }

                    if (!\in_array($requestedProfileId, $availableProfiles, true)) {
                        throw new HttpException('no such "profile_id"', 404);
                    }

                    $profileConfig = $this->config->profileConfig($requestedProfileId);
                    $preferTcp = 'yes' === $request->optionalPostParameter('prefer_tcp', fn (string $s) => Validator::yesOrNo($s));
                    if (null === $displayName = $request->optionalPostParameter('display_name', fn (string $s) => Validator::displayName($s))) {
                        $displayName = 'Admin API';
                    }

                    $secretKey = Key::generate();
                    $clientConfig = $this->connectionManager->connect(
                        $this->serverInfo,
                        $profileConfig,
                        $userInfo->userId(),
                        Protocol::parseMimeType($request->optionalHeader('HTTP_ACCEPT')),
                        $displayName,
                        $this->sessionExpiry->expiresAt(),
                        $preferTcp,
                        Key::publicKeyFromSecretKey($secretKey),
                        null
                    );

                    if ($clientConfig instanceof WireGuardClientConfig) {
                        $clientConfig->setPrivateKey($secretKey);
                    }

                    return new Response(
                        $clientConfig->get(),
                        [
                            'Expires' => $this->sessionExpiry->expiresAt()->format(DateTimeImmutable::RFC7231),
                            'Content-Type' => $clientConfig->contentType(),
                        ]
                    );
                } catch (ProtocolException $e) {
                    throw new HttpException($e->getMessage(), 406);
                } catch (ConnectionManagerException $e) {
                    throw new HttpException(sprintf('/v1/create failed: %s', $e->getMessage()), 500);
                }
            }
        );

        $service->post(
            '/v1/destroy',
            function (Request $request, UserInfo $userInfo): Response {
                try {
                    // we will destroy all active configurations for this user
                    $this->connectionManager->disconnectByUserId($userInfo->userId());
                    // delete the user account as well
                    $this->storage->userDelete($userInfo->userId());

                    return new Response(null, [], 204);
                } catch (ConnectionManagerException $e) {
                    throw new HttpException(sprintf('/v1/destroy failed: %s', $e->getMessage()), 500);
                }
            }
        );
    }
}
