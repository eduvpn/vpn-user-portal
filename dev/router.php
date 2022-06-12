<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 *
 * This script can be used as a router script for PHP's built-in webserver.
 * To start the vpn-user-portal using this router, use a command like:
 *
 * $ php -S localhost:8082 -t web dev/router.php
 */

chdir(__DIR__ . "/../web");

// Extract the normalized path from the request.
$request = parse_url($_SERVER['REQUEST_URI']);
$path = preg_replace('!//+!', '/', $request['path']);

// Take care of virtual request paths. 
if ('/.well-known/vpn-user-portal' === $path) {
    include 'well-known.php';
} else if ('/oauth/authorize' === $path) {
    include 'index.php';
} else if ('/oauth/token' === $path) {
    include 'oauth.php';
} else if ('/api' === $path) {
    include 'api.php';
} else {
    // No special handling required. Pass on the responsibility
    // for handling the request to the PHP builtin webserver.
    return false;
}
