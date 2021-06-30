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
use LC\Portal\Config;
use LC\Portal\Dt;
use LC\Portal\Exception\NodeApiException;
use LC\Portal\LoggerInterface;
use LC\Portal\ServerConfig;
use LC\Portal\Storage;

class NodeApiModule implements ServiceModuleInterface
{
    private Config $config;

    private Storage $storage;

    private ServerConfig $serverConfig;

    private DateTimeImmutable $dateTime;

    public function __construct(Config $config, Storage $storage, ServerConfig $serverConfig)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->serverConfig = $serverConfig;
        $this->dateTime = Dt::get();
    }

    public function init(Service $service): void
    {
        $service->post(
            '/server_config',
            function (UserInfo $userInfo, Request $request): Response {
                // XXX we may want to restrict the profiles for particular nodes!
                $serverConfigList = $this->serverConfig->get($this->config->profileConfigList());
                $bodyLines = [];
                foreach ($serverConfigList as $configName => $configFile) {
                    $bodyLines[] = $configName.':'.sodium_bin2base64($configFile, \SODIUM_BASE64_VARIANT_ORIGINAL);
                }

                /// XXX content type?
                return new Response(implode("\r\n", $bodyLines));
            }
        );

        $service->post(
            '/connect',
            function (UserInfo $userInfo, Request $request): Response {
                try {
                    $this->connect($request);

                    return new Response('OK');
                } catch (NodeApiException $e) {
                    if (null !== $userId = $e->getUserId()) {
                        $this->storage->addUserLog($userId, LoggerInterface::ERROR, 'unable to connect: '.$e->getMessage(), $this->dateTime);
                    }

                    return new Response('ERR');
                }
            }
        );

        $service->post(
            '/disconnect',
            function (UserInfo $userInfo, Request $request): Response {
                try {
                    $this->disconnect($request);

                    return new Response('OK');
                } catch (NodeApiException $e) {
                    if (null !== $userId = $e->getUserId()) {
                        $this->storage->addUserLog($userId, LoggerInterface::ERROR, 'unable to disconnect: '.$e->getMessage(), $this->dateTime);
                    }

                    return new Response('ERR');
                }
            }
        );
    }

    public function connect(Request $request): void
    {
        $profileId = InputValidation::profileId($request->requirePostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->requirePostParameter('common_name'));
        $ipFour = InputValidation::ipFour($request->requirePostParameter('ip_four'));
        $ipSix = InputValidation::ipSix($request->requirePostParameter('ip_six'));
        $connectedAt = InputValidation::connectedAt($request->requirePostParameter('connected_at'));

        $this->verifyConnection($profileId, $commonName);
        $this->storage->clientConnect($profileId, $commonName, $ipFour, $ipSix, Dt::get(sprintf('@%d', $connectedAt)));
    }

    public function disconnect(Request $request): void
    {
        $profileId = InputValidation::profileId($request->requirePostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->requirePostParameter('common_name'));
        $ipFour = InputValidation::ipFour($request->requirePostParameter('ip_four'));
        $ipSix = InputValidation::ipSix($request->requirePostParameter('ip_six'));
        $disconnectedAt = InputValidation::disconnectedAt($request->requirePostParameter('disconnected_at'));
        $bytesTransferred = InputValidation::bytesTransferred($request->requirePostParameter('bytes_transferred'));

        $this->storage->clientDisconnect($profileId, $commonName, $ipFour, $ipSix, Dt::get(sprintf('@%d', $disconnectedAt)), $bytesTransferred);
    }

    private function verifyConnection(string $profileId, string $commonName): void
    {
        // verify status of certificate/user
        if (null === $userCertInfo = $this->storage->getUserCertificateInfo($commonName)) {
            // we do not (yet) know the user as only an existing *//* certificate can be linked back to a user...
            throw new NodeApiException(null, sprintf('user or certificate does not exist [profile_id: %s, common_name: %s]', $profileId, $commonName));
        }

        $userId = $userCertInfo['user_id'];
        if ($userCertInfo['user_is_disabled']) {
            throw new NodeApiException($userId, 'unable to connect, account is disabled');
        }

        $this->verifyAcl($profileId, $userId);
    }

    private function verifyAcl(string $profileId, string $userId): void
    {
        $profileConfig = $this->config->profileConfig($profileId);
        if ($profileConfig->enableAcl()) {
            // ACL is enabled for this profile
            $userPermissionList = $this->storage->getPermissionList($userId);
            $profilePermissionList = $profileConfig->aclPermissionList();
            if (false === self::hasPermission($userPermissionList, $profilePermissionList)) {
                throw new NodeApiException($userId, sprintf('unable to connect, user permissions are [%s], but requires any of [%s]', implode(',', $userPermissionList), implode(',', $profilePermissionList)));
            }
        }
    }

    private static function hasPermission(array $userPermissionList, array $aclPermissionList): bool
    {
        // one of the permissions must be listed in the profile ACL list
        foreach ($userPermissionList as $userPermission) {
            if (\in_array($userPermission, $aclPermissionList, true)) {
                return true;
            }
        }

        return false;
    }
}
