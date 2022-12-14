<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use DateTimeImmutable;
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use fkooman\OAuth\Server\Signer;
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\Http\VpnApiThreeModule;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;

class TestVpnApiThreeModule extends VpnApiThreeModule
{
    public string $secretKey = 'k7.sec.TUuaHeqpfURSUR_x.0QeCqFrr5rxbMl4yrRKQZ1vke1650IaAlTqb6xJqaVsA6SdM0S_DG8lHTFdFrIk_C4EL6EBOYBGXuiqDrLxz9Q';

    public function __construct(Config $config, Storage $storage, ServerInfo $serverInfo, ConnectionManager $connectionManager)
    {
        $oauthStorage = new OAuthStorage($storage->dbPdo(), 'oauth_');
        parent::__construct($config, $storage, $oauthStorage, $serverInfo, $connectionManager, new Signer($this->secretKey));
        $this->dateTime = new DateTimeImmutable('2022-01-01T09:00:00+00:00');
    }
}
