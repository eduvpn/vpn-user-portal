<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

interface ConnectionHookInterface
{
    /**
     * @throws \Vpn\Portal\Exception\ConnectionHookException
     */
    public function connect(string $userId, string $profileId, string $vpnProto, string $connectionId, string $ipFour, string $ipSix, ?string $originatingIp): void;

    /**
     * @throws \Vpn\Portal\Exception\ConnectionHookException
     */
    public function disconnect(string $userId, string $profileId, string $vpnProto, string $connectionId, string $ipFour, string $ipSix, int $bytesIn, int $bytesOut): void;
}
