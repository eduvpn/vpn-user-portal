<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use Vpn\Portal\Environment;
use Vpn\Portal\FileIO;
use Vpn\Portal\Http\JsonResponse;
use Vpn\Portal\Http\Request;

try {
    Environment::verify();
    $request = Request::createFromGlobals();

    if (false === $appRoot = getenv('VPN_APP_ROOT')) {
        $appRoot = '';
    }
    $appRootUri = $request->getScheme().'://'.$request->getAuthority().$appRoot;
    $jsonData = [
        'api' => [
            'http://eduvpn.org/api#3' => [
                'api_endpoint' => $appRootUri.'/api/v3',
                'authorization_endpoint' => $appRootUri.'/oauth/authorize',
                'token_endpoint' => $appRootUri.'/oauth/token',
            ],
        ],
        'v' => trim(FileIO::read($baseDir.'/VERSION')),
    ];

    $response = new JsonResponse($jsonData, ['Cache-Control' => 'no-store']);
    $response->send();
} catch (Exception $e) {
    $response = new JsonResponse(['error' => $e->getMessage()], [], 500);
    $response->send();
}
