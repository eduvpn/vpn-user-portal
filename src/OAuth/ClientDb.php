<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OAuth;

use fkooman\OAuth\Server\ClientInfo;
use fkooman\OAuth\Server\SimpleClientDb;

class ClientDb extends SimpleClientDb
{
    public function __construct()
    {
        $this->add(
            new ClientInfo(
                'org.eduvpn.app.windows', ['http://127.0.0.1:{PORT}/callback', 'http://[::1]:{PORT}/callback'], null, 'eduVPN for Windows', true
            )
        );
        $this->add(
            new ClientInfo(
                'org.letsconnect-vpn.app.windows', ['http://127.0.0.1:{PORT}/callback', 'http://[::1]:{PORT}/callback'], null, 'Let\'s Connect! for Windows', true
            )
        );
        $this->add(
            new ClientInfo(
                'org.eduvpn.app.android', ['org.eduvpn.app:/api/callback', 'https://android.app.eduvpn.org/api/callback'], null, 'eduVPN for Android', true
            )
        );
        $this->add(
            new ClientInfo(
                'org.letsconnect-vpn.app.android', ['org.letsconnect-vpn.app:/api/callback', 'https://android.app.letsconnect-vpn.org/api/callback'], null, 'Let\'s Connect! for Android', true
            )
        );
        $this->add(
            new ClientInfo(
                'org.eduvpn.app.ios', ['org.eduvpn.app.ios:/api/callback', 'https://ios.app.eduvpn.org/auth/app/redirect/'], null, 'eduVPN for iOS', true
            )
        );
        $this->add(
            new ClientInfo(
                'org.letsconnect-vpn.app.ios', ['org.letsconnect-vpn.app.ios:/api/callback'], null, 'Let\'s Connect! for iOS', true
            )
        );
        $this->add(
            new ClientInfo(
                'org.eduvpn.app.macos', ['org.eduvpn.app:/api/callback', 'http://127.0.0.1:{PORT}/callback', 'http://[::1]:{PORT}/callback'], null, 'eduVPN for macOS', true
            )
        );
        $this->add(
            new ClientInfo(
                'org.letsconnect-vpn.app.macos', ['org.letsconnect-vpn.app:/api/callback', 'http://127.0.0.1:{PORT}/callback', 'http://[::1]:{PORT}/callback'], null, 'Let\'s Connect! for macOS', true
            )
        );
        $this->add(
            new ClientInfo(
                'org.eduvpn.app.linux', ['org.eduvpn.app:/api/callback', 'http://127.0.0.1:{PORT}/callback', 'http://[::1]:{PORT}/callback'], null, 'eduVPN for Linux', true
            )
        );
        $this->add(
            new ClientInfo(
                'org.letsconnect-vpn.app.linux', ['org.letsconnect-vpn.app:/api/callback', 'http://127.0.0.1:{PORT}/callback', 'http://[::1]:{PORT}/callback'], null, 'Let\'s Connect! for Linux', true
            )
        );
    }
}
