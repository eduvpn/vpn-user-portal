<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';

use Vpn\Portal\ScriptConnectionHook;

$scriptConnectionHook = new ScriptConnectionHook(
    __DIR__.'/test_script.sh'
);

$scriptConnectionHook->connect(
    'user_id',
    'profile_id',
    'openvpn',
    'connection_id',
    '10.0.0.99',
    'fd99::99',
    '192.168.0.99'
);

$scriptConnectionHook->disconnect(
    'user_id',
    'profile_id',
    'openvpn',
    'connection_id',
    '10.0.0.99',
    'fd99::99',
    12345,
    54321
);
