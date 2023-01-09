<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
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

if ('/.well-known/vpn-user-portal' === $requestPath) {
    include $webDir.'/well-known.php';

    return;
}

if ('/oauth/authorize' === $requestPath) {
    include $webDir.'/index.php';

    return;
}

if ('/oauth/token' === $requestPath) {
    include $webDir.'/oauth.php';

    return;
}

if (0 === strpos($requestPath, '/api/')) {
    $_SERVER['PATH_INFO'] = substr($_SERVER['PATH_INFO'], 4);

    include $webDir.'/api.php';

    return;
}

if (0 === strpos($requestPath, '/admin/api/')) {
    include $webDir.'/admin-api.php';

    return;
}

// no special handling required, pass on the responsibility for handling the
// request to the PHP builtin webserver
return false;
