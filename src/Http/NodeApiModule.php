<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ConnectionHookInterface;
use Vpn\Portal\Http\Exception\NodeApiException;
use Vpn\Portal\LoggerInterface;
use Vpn\Portal\ServerConfig;
use Vpn\Portal\Storage;
use Vpn\Portal\Validator;

class NodeApiModule implements ServiceModuleInterface
{
    private Config $config;
    private Storage $storage;
    private ServerConfig $serverConfig;
    private LoggerInterface $logger;
    private ConnectionHookInterface $connectionHook;

    public function __construct(Config $config, Storage $storage, ServerConfig $serverConfig, ConnectionHookInterface $connectionHook, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->serverConfig = $serverConfig;
        $this->logger = $logger;
        $this->connectionHook = $connectionHook;
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
            $nodeNumber = (int) $request->requireHeader('HTTP_X_NODE_NUMBER', fn (string $s) => Validator::nodeNumber($s));
            $commonName = $request->requirePostParameter('common_name', fn (string $s) => Validator::commonName($s));
            $originatingIp = $request->requirePostParameter('originating_ip', fn (string $s) => Validator::ipAddress($s));
            $ipFour = $request->requirePostParameter('ip_four', fn (string $s) => Validator::ipFour($s));
            $ipSix = $request->requirePostParameter('ip_six', fn (string $s) => Validator::ipSix($s));
            $userId = $this->verifyConnection($profileId, $nodeNumber, $commonName);
            $this->connectionHook->connect($userId, $profileId, 'openvpn', $commonName, $ipFour, $ipSix, $originatingIp);

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
        $ipFour = $request->requirePostParameter('ip_four', fn (string $s) => Validator::ipFour($s));
        $ipSix = $request->requirePostParameter('ip_six', fn (string $s) => Validator::ipSix($s));
        $bytesIn = (int) $request->requirePostParameter('bytes_in', fn (string $s) => Validator::nonNegativeInt($s));
        $bytesOut = (int) $request->requirePostParameter('bytes_out', fn (string $s) => Validator::nonNegativeInt($s));
        // because OpenVPN disconnects are only triggered after the client
        // disconnects from the OpenVPN server process, we can't always use
        // the "certificates" table to find the user_id from there as the
        // certificate might have been deleted already in ConnectionManager
        if (null === $userId = $this->storage->userIdFromConnectionLog($commonName)) {
            $this->logger->warning(sprintf('unable to find user belonging to CN "%s"', $commonName));

            return new Response('OK');
        }

        $this->connectionHook->disconnect($userId, $profileId, 'openvpn', $commonName, $ipFour, $ipSix, $bytesIn, $bytesOut);

        return new Response('OK');
    }

    private function verifyConnection(string $profileId, int $nodeNumber, string $commonName): string
    {
        if (null === $oCertInfo = $this->storage->oCertInfo($commonName)) {
            throw new NodeApiException(sprintf('unable to find certificate with CN "%s" in the database', $commonName));
        }

        $userId = $oCertInfo['user_id'];
        if ($oCertInfo['user_is_disabled']) {
            throw new NodeApiException(sprintf('account "%s" has been disabled', $userId));
        }

        // make sure the profileId the client connects to matches the one in
        // the certificate
        if ($profileId !== $oCertInfo['profile_id']) {
            throw new NodeApiException(sprintf('certificate "%s" for profile "%s" can not be used with profile "%s"', $commonName, $oCertInfo['profile_id'], $profileId));
        }

        // make sure the nodeNumber the client connects to matches the one in
        // the certificate
        if ($nodeNumber !== $oCertInfo['node_number']) {
            throw new NodeApiException(sprintf('certificate "%s" for node "%d" can not be used with node "%d"', $commonName, $oCertInfo['node_number'], $nodeNumber));
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
}
