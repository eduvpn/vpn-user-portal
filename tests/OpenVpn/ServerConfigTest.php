<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\OpenVpn;

use DateTime;
use LC\Portal\Config\PortalConfig;
use LC\Portal\OpenVpn\ServerConfig;
use LC\Portal\OpenVpn\TlsCrypt;
use LC\Portal\Tests\TestCa;
use PHPUnit\Framework\TestCase;

class ServerConfigTest extends TestCase
{
    public function testDefaultConfig()
    {
        $serverConfig = new ServerConfig(
            PortalConfig::fromFile(\dirname(\dirname(__DIR__)).'/config/config.php.example'),
            new TestCa(new DateTime('2019-01-01')),
            TlsCrypt::fromFile(\dirname(__DIR__).'/tls-crypt.key'),
            ServerConfig::OS_REDHAT
        );
        $serverConfig->setDateTime(new DateTime('2019-01-01'));
        // file_put_contents(__DIR__.'/default.conf', var_export($serverConfig->getConfigList(), true));
        $this->assertSame(
            file_get_contents(__DIR__.'/default.conf'),
            var_export($serverConfig->getConfigList(), true)
        );
    }
}
