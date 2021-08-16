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

    private LoggerInterface $logger;

    private DateTimeImmutable $dateTime;

    public function __construct(Config $config, Storage $storage, ServerConfig $serverConfig, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->serverConfig = $serverConfig;
        $this->logger = $logger;
        $this->dateTime = Dt::get();
    }

    public function init(ServiceInterface $service): void
    {
        $service->post(
            '/server_config',
            function (UserInfo $userInfo, Request $request): Response {
                // XXX we may want to restrict the profiles for particular nodes!
                $serverConfigList = $this->serverConfig->get($this->config->profileConfigList());
                $bodyLines = [];
                foreach ($serverConfigList as $configName => $configFile) {
                    $bodyLines[] = $configName.':'.sodium_bin2base64($configFile, SODIUM_BASE64_VARIANT_ORIGINAL);
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
        $profileId = $request->requirePostParameter('profile_id', fn (string $s) => InputValidation::re($s, InputValidation::REGEXP_PROFILE_ID));
        $commonName = $request->requirePostParameter('common_name', fn (string $s) => InputValidation::re($s, InputValidation::REGEXP_COMMON_NAME));
        $originatingIp = $request->requirePostParameter('originating_ip', fn (string $s) => InputValidation::ipAddress($s));
        $ipFour = $request->requirePostParameter('ip_four', fn (string $s) => InputValidation::ipFour($s));
        $ipSix = $request->requirePostParameter('ip_six', fn (string $s) => InputValidation::ipSix($s));
        $connectedAt = (int) $request->requirePostParameter('connected_at', fn (string $s) => is_numeric($s) && $s > 0);
        $userId = $this->verifyConnection($profileId, $commonName);
        $this->storage->clientConnect($userId, $profileId, $ipFour, $ipSix, Dt::get(sprintf('@%d', $connectedAt)));
        $this->logger->info(
            $this->logMessage('CONNECT', $userId, $profileId, $originatingIp, $ipFour, $ipSix)
        );
    }

    public function disconnect(Request $request): void
    {
        $profileId = $request->requirePostParameter('profile_id', fn (string $s) => InputValidation::re($s, InputValidation::REGEXP_PROFILE_ID));
        $commonName = $request->requirePostParameter('common_name', fn (string $s) => InputValidation::re($s, InputValidation::REGEXP_COMMON_NAME));
        $originatingIp = $request->requirePostParameter('originating_ip', fn (string $s) => InputValidation::ipAddress($s));
        $ipFour = $request->requirePostParameter('ip_four', fn (string $s) => InputValidation::ipFour($s));
        $ipSix = $request->requirePostParameter('ip_six', fn (string $s) => InputValidation::ipSix($s));
        $disconnectedAt = (int) $request->requirePostParameter('disconnected_at', fn (string $s) => is_numeric($s) && $s > 0);
//        $bytesTransferred = InputValidation::bytesTransferred($request->requirePostParameter('bytes_transferred'));
        // XXX add bytesTransferred to some global table

        if (null === $certInfo = $this->storage->getUserCertificateInfo($commonName)) {
            // CN does not exist (anymore)
            // XXX do we need to do something special?
            return;
        }
        $userId = $certInfo['user_id'];
        $this->storage->clientDisconnect($userId, $profileId, $ipFour, $ipSix, Dt::get(sprintf('@%d', $disconnectedAt)));
        $this->logger->info(
            $this->logMessage('DISCONNECT', $userId, $profileId, $originatingIp, $ipFour, $ipSix)
        );
    }

    private function verifyConnection(string $profileId, string $commonName): string
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

        return $userId;
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

    private function logMessage(string $eventType, string $userId, string $profileId, string $originatingIp, string $ipFour, string $ipSix): string
    {
        return str_replace(
            [
                '{{EVENT_TYPE}}',
                '{{USER_ID}}',
                '{{PROFILE_ID}}',
                '{{ORIGINATING_IP}}',
                '{{IP_FOUR}}',
                '{{IP_SIX}}',
            ],
            [
                $eventType,
                $userId,
                $profileId,
                $originatingIp,
                $ipFour,
                $ipSix,
            ],
            $this->config->connectionLogFormat()
        );
    }
}
