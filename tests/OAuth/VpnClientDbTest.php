<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\OAuth\Tests;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\OAuth\VpnClientDb;

class VpnClientDbTest extends TestCase
{
    public function testWindows(): void
    {
        $clientDb = new VpnClientDb(__DIR__.'/does_not_exist.json');
        $clientInfo = $clientDb->get('org.eduvpn.app.windows');
        $this->assertSame('org.eduvpn.app.windows', $clientInfo->clientId());
        $this->assertSame('eduVPN for Windows', $clientInfo->displayName());

        $clientInfo = $clientDb->get('org.letsconnect-vpn.app.windows');
        $this->assertSame('org.letsconnect-vpn.app.windows', $clientInfo->clientId());
        $this->assertSame('Let\'s Connect! for Windows', $clientInfo->displayName());
    }

    public function testAndroid(): void
    {
        $clientDb = new VpnClientDb(__DIR__.'/does_not_exist.json');
        $clientInfo = $clientDb->get('org.eduvpn.app.android');
        $this->assertSame('org.eduvpn.app.android', $clientInfo->clientId());
        $this->assertSame('eduVPN for Android', $clientInfo->displayName());
        $this->assertTrue($clientInfo->isValidRedirectUri('org.eduvpn.app:/api/callback'));

        $clientInfo = $clientDb->get('org.letsconnect-vpn.app.android');
        $this->assertSame('org.letsconnect-vpn.app.android', $clientInfo->clientId());
        $this->assertSame('Let\'s Connect! for Android', $clientInfo->displayName());
        $this->assertTrue($clientInfo->isValidRedirectUri('org.letsconnect-vpn.app:/api/callback'));
    }

    public function testiOS(): void
    {
        $clientDb = new VpnClientDb(__DIR__.'/does_not_exist.json');
        $clientInfo = $clientDb->get('org.eduvpn.app.ios');
        $this->assertSame('org.eduvpn.app.ios', $clientInfo->clientId());
        $this->assertSame('eduVPN for iOS', $clientInfo->displayName());
        $this->assertTrue($clientInfo->isValidRedirectUri('org.eduvpn.app.ios:/api/callback'));

        $clientInfo = $clientDb->get('org.letsconnect-vpn.app.ios');
        $this->assertSame('org.letsconnect-vpn.app.ios', $clientInfo->clientId());
        $this->assertSame('Let\'s Connect! for iOS', $clientInfo->displayName());
        $this->assertTrue($clientInfo->isValidRedirectUri('org.letsconnect-vpn.app.ios:/api/callback'));
    }

    public function testJsonFile(): void
    {
        $clientDb = new VpnClientDb(__DIR__.'/oauth_client_db.json');
        $clientInfo = $clientDb->get('foo');
        $this->assertNotNull($clientInfo);
        $this->assertSame('foo', $clientInfo->clientId());
        $this->assertSame(['https://foo.example.org/callback'], $clientInfo->redirectUriList());
    }
}
