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
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\Http\VpnApiThreeModule;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;

class TestVpnApiThreeModule extends VpnApiThreeModule
{
    public function __construct(Config $config, Storage $storage, ServerInfo $serverInfo, ConnectionManager $connectionManager)
    {
        $oauthStorage = new OAuthStorage($storage->dbPdo(), 'oauth_');
        parent::__construct($config, $storage, $oauthStorage, $serverInfo, $connectionManager);
        $this->dateTime = new DateTimeImmutable('2022-01-01T09:00:00+00:00');
    }
}
