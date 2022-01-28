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
use Vpn\Portal\Config;
use Vpn\Portal\Dt;
use Vpn\Portal\Http\Exception\NodeApiException;
use Vpn\Portal\LoggerInterface;
use Vpn\Portal\ServerConfig;
use Vpn\Portal\Storage;
use Vpn\Portal\Validator;

class NodeApiModule implements ServiceModuleInterface
{
    protected DateTimeImmutable $dateTime;
    private Config $config;
    private Storage $storage;
    private ServerConfig $serverConfig;
    private LoggerInterface $logger;

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
            function (Request $request, UserInfo $userInfo): Response {
                // XXX catch exceptions
                $profileConfigList = $this->config->profileConfigList();
                $profileIdList = $request->optionalArrayPostParameter('profile_id_list', fn (array $a) => Validator::profileIdList($a));
                if (0 !== \count($profileIdList)) {
                    $profileConfigList = [];
                    foreach ($profileIdList as $profileId) {
                        $profileConfigList[] = $this->config->profileConfig($profileId);
                    }
                }

                $serverConfigList = $this->serverConfig->get(
                    $profileConfigList,
                    (int) $userInfo->userId(), // userId = nodeNumber
                    $request->requirePostParameter('public_key', fn (string $s) => Validator::publicKey($s)),
                    'yes' === $request->requirePostParameter('prefer_aes', fn (string $s) => Validator::yesOrNo($s))
                );

                return new JsonResponse($serverConfigList);
            }
        );

        $service->post(
            '/connect',
            fn (Request $request, UserInfo $userInfo): Response => $this->connect($request)
        );

        $service->post(
            '/disconnect',
            fn (Request $request, UserInfo $userInfo): Response => $this->disconnect($request)
        );
    }

    public function connect(Request $request): Response
    {
        try {
            $profileId = $request->requirePostParameter('profile_id', fn (string $s) => Validator::profileId($s));
            $commonName = $request->requirePostParameter('common_name', fn (string $s) => Validator::commonName($s));
            $originatingIp = $request->requirePostParameter('originating_ip', fn (string $s) => Validator::ipAddress($s));
            $ipFour = $request->requirePostParameter('ip_four', fn (string $s) => Validator::ipFour($s));
            $ipSix = $request->requirePostParameter('ip_six', fn (string $s) => Validator::ipSix($s));
            $userId = $this->verifyConnection($profileId, $commonName);
            $this->storage->clientConnect($userId, $profileId, 'openvpn', $commonName, $ipFour, $ipSix, $this->dateTime);
            $this->logger->info(
                $this->logMessage('CONNECT', $userId, $profileId, $commonName, $originatingIp, $ipFour, $ipSix)
            );

            return new Response('OK');
        } catch (NodeApiException $e) {
            $this->logger->warning($e->getMessage());

            return new Response('ERR');
        }
    }

    public function disconnect(Request $request): Response
    {
        $profileId = $request->requirePostParameter('profile_id', fn (string $s) => Validator::profileId($s));
        $commonName = $request->requirePostParameter('common_name', fn (string $s) => Validator::commonName($s));
        $originatingIp = $request->requirePostParameter('originating_ip', fn (string $s) => Validator::ipAddress($s));
        $ipFour = $request->requirePostParameter('ip_four', fn (string $s) => Validator::ipFour($s));
        $ipSix = $request->requirePostParameter('ip_six', fn (string $s) => Validator::ipSix($s));
        $bytesIn = (int) $request->requirePostParameter('bytes_in', fn (string $s) => Validator::nonNegativeInt($s));
        $bytesOut = (int) $request->requirePostParameter('bytes_out', fn (string $s) => Validator::nonNegativeInt($s));

        // try to find the "open" connection in the connection_log table, i.e.
        // where "disconnected_at IS NULL", if found "close" the connection
        if (null !== $userId = $this->storage->oUserIdFromConnectionLog($profileId, $commonName)) {
            $this->storage->clientDisconnect($userId, $profileId, $commonName, $bytesIn, $bytesOut, $this->dateTime);
        }
        $this->logger->info(
            $this->logMessage('DISCONNECT', $userId ?? 'N/A', $profileId, $commonName, $originatingIp, $ipFour, $ipSix)
        );

        return new Response('OK');
    }

    private function verifyConnection(string $profileId, string $commonName): string
    {
        if (null === $userInfo = $this->storage->oUserInfoByProfileIdAndCommonName($profileId, $commonName)) {
            throw new NodeApiException(sprintf('unable to find certificate with CN "%s" in the database', $commonName));
        }

        $userId = $userInfo['user_id'];
        if ($userInfo['user_is_disabled']) {
            throw new NodeApiException(sprintf('account "%s" has been disabled', $userId));
        }

        $profileConfig = $this->config->profileConfig($profileId);
        if (null !== $profilePermissionList = $profileConfig->aclPermissionList()) {
            // ACL is enabled for this profile
            $userPermissionList = $this->storage->userPermissionList($userId);
            if (false === self::hasPermission($userPermissionList, $profilePermissionList)) {
                throw new NodeApiException(sprintf('account "%s" has insufficient permissions to access profile "%s"', $userId, $profileId));
            }
        }

        return $userId;
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

    private function logMessage(string $eventType, string $userId, string $profileId, string $connectionId, string $originatingIp, string $ipFour, string $ipSix): string
    {
        return str_replace(
            [
                '{{EVENT_TYPE}}',
                '{{USER_ID}}',
                '{{PROFILE_ID}}',
                '{{CONNECTION_ID}}',
                '{{ORIGINATING_IP}}',
                '{{IP_FOUR}}',
                '{{IP_SIX}}',
            ],
            [
                $eventType,
                $userId,
                $profileId,
                $connectionId,
                $originatingIp,
                $ipFour,
                $ipSix,
            ],
            $this->config->connectionLogFormat()
        );
    }
}
