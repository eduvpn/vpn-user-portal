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

chdir(__DIR__.'/../web');

// Extract the path from the request.
$request = parse_url($_SERVER['REQUEST_URI']);
$path = $request['path'];

// Take care of virtual request paths.
if ('/.well-known/vpn-user-portal' === $path) {
    include 'well-known.php';
} elseif ('/oauth/authorize' === $path) {
    include 'index.php';
} elseif ('/oauth/token' === $path) {
    include 'oauth.php';
} elseif ('/api' === $path) {
    include 'api.php';
} else {
    // No special handling required. Pass on the responsibility
    // for handling the request to the PHP builtin webserver.
    return false;
}
