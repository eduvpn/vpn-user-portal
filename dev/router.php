<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

/*
 * This script can be used as a router script for PHP's built-in webserver.
 * To start the vpn-user-portal using this router, use a command like:
 *
 * $ php -S localhost:8082 -t web dev/router.php
 */

$webDir = dirname(__DIR__).'/web';
$requestUri = parse_url($_SERVER['REQUEST_URI']);
$requestPath = $requestUri['path'];

switch ($requestPath) {
    case '/.well-known/vpn-user-portal':
        include $webDir.'/well-known.php';

        break;

    case '/oauth/authorize':
        include $webDir.'/index.php';

        break;

    case '/oauth/token':
        include $webDir.'/oauth.php';

        break;

    case '/api':
        include $webDir.'/api.php';

        break;

    default:
        // no special handling required. Pass on the responsibility for
        // handling the request to the PHP builtin webserver
        return false;
}
