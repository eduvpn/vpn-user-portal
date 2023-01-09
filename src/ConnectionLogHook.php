<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use DateTimeImmutable;

/**
 * Write new entry in connection_log table on "connect", close the entry on
 * "disconnect".
 */
class ConnectionLogHook implements ConnectionHookInterface
{
    protected DateTimeImmutable $dateTime;
    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
        $this->dateTime = Dt::get();
    }

    public function connect(string $userId, string $profileId, string $vpnProto, string $connectionId, string $ipFour, string $ipSix, ?string $originatingIp): void
    {
        $this->storage->clientConnect($userId, $profileId, $vpnProto, $connectionId, $ipFour, $ipSix, $this->dateTime);
    }

    public function disconnect(string $userId, string $profileId, string $vpnProto, string $connectionId, string $ipFour, string $ipSix, int $bytesIn, int $bytesOut): void
    {
        $this->storage->clientDisconnect($connectionId, $bytesIn, $bytesOut, $this->dateTime);
    }
}
