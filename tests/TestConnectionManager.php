<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use DateTimeImmutable;
use Vpn\Portal\Config;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\LoggerInterface;
use Vpn\Portal\Storage;
use Vpn\Portal\VpnDaemon;

class TestConnectionManager extends ConnectionManager
{
    public function __construct(Config $config, VpnDaemon $vpnDaemon, Storage $storage, LoggerInterface $logger)
    {
        parent::__construct($config, $vpnDaemon, $storage, $logger);
        $this->random = new TestRandom();
        $this->dateTime = new DateTimeImmutable('2022-01-01T09:00:00+00:00');
    }
}
