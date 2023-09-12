<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\Cfg\LogConfig;

/**
 * Write to (sys)logger on connect/disconnect events.
 */
class LogConnectionHook implements ConnectionHookInterface
{
    private Storage $storage;
    private LoggerInterface $logger;
    private LogConfig $logConfig;

    public function __construct(Storage $storage, LoggerInterface $logger, LogConfig $logConfig)
    {
        $this->storage = $storage;
        $this->logger = $logger;
        $this->logConfig = $logConfig;
    }

    public function connect(string $userId, string $profileId, string $vpnProto, string $connectionId, string $ipFour, string $ipSix, ?string $originatingIp): void
    {
        $this->logger->info(
            self::logConnect($userId, $profileId, $connectionId, $ipFour, $ipSix, $originatingIp)
        );
    }

    public function disconnect(string $userId, string $profileId, string $vpnProto, string $connectionId, string $ipFour, string $ipSix, int $bytesIn, int $bytesOut): void
    {
        $this->logger->info(
            self::logDisconnect($userId, $profileId, $connectionId, $ipFour, $ipSix)
        );
    }

    private function logConnect(string $userId, string $profileId, string $connectionId, string $ipFour, string $ipSix, ?string $originatingIp): string
    {
        if (!$this->logConfig->originatingIp() || null === $originatingIp) {
            $originatingIp = '*';
        }

        $logMsg = sprintf(
            'CONNECT %s (%s:%s) [%s => %s,%s]',
            $userId,
            $profileId,
            $connectionId,
            $originatingIp,
            $ipFour,
            $ipSix
        );

        if ($this->logConfig->authData()) {
            $logMsg .= sprintf(' [AUTH_DATA=%s]', $this->authData($userId));
        }

        return $logMsg;
    }

    private function logDisconnect(string $userId, string $profileId, string $connectionId, string $ipFour, string $ipSix): string
    {
        return sprintf(
            'DISCONNECT %s (%s:%s)',
            $userId,
            $profileId,
            $connectionId
        );
    }

    /**
     * Obtain the Base64 URL safe encoded "auth_data" column for this user from
     * the database. It is currently used to store the originating local user
     * before HMAC'ing the user's identifier for use with "Guest Access".
     */
    private function authData(string $userId): string
    {
        if (null === $userInfo = $this->storage->userInfo($userId)) {
            // user no longer exists
            return '';
        }
        if (null === $authData = $userInfo->authData()) {
            // no auth_data for this user
            return '';
        }

        return Base64UrlSafe::encodeUnpadded($authData);
    }
}
