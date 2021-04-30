<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Portal\FileIO;
use LC\Portal\Http\JsonResponse;
use LC\Portal\Http\Request;

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    if (false === $appRoot = getenv('VPN_APP_ROOT')) {
        $appRootUri = $request->getScheme().'://'.$request->getAuthority();
    } else {
        $appRootUri = $request->getScheme().'://'.$request->getAuthority().$appRoot;
    }

    $jsonData = [
        'api' => [
            'http://eduvpn.org/api#2' => [
                'api_base_uri' => $appRootUri.'/api/v2',
                'authorization_endpoint' => $appRootUri.'/oauth/authorize',
                'token_endpoint' => $appRootUri.'/oauth/token',
            ],
            'http://eduvpn.org/api#3' => [
                'api_endpoint' => $appRootUri.'/api/v3',
                'authorization_endpoint' => $appRootUri.'/oauth/authorize',
                'token_endpoint' => $appRootUri.'/oauth/token',
            ],
        ],
        'v' => trim(FileIO::readFile($baseDir.'/VERSION')),
    ];

    $response = new JsonResponse($jsonData, ['Cache-Control' => 'no-store']);
    $response->send();
} catch (Exception $e) {
    $response = new JsonResponse(['error' => $e->getMessage()], [], 500);
    $response->send();
}
