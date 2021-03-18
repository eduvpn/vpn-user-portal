<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Common\FileIO;
use LC\Common\Http\JsonResponse;
use LC\Common\Http\Request;

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    if (false === $appRoot = getenv('VPN_APP_ROOT')) {
        $appRootUri = sprintf('%s://%s', $request->getScheme(), $request->getAuthority());
    } else {
        $appRootUri = sprintf('%s://%s%s', $request->getScheme(), $request->getAuthority(), $appRoot);
    }

    $jsonData = [
        'api' => [
            'http://eduvpn.org/api#2' => [
                'api_base_uri' => $appRootUri.'/api.php',
                'authorization_endpoint' => $appRootUri.'/_oauth/authorize',
                'token_endpoint' => $appRootUri.'/oauth.php/token',
            ],
        ],
        'v' => trim(FileIO::readFile(sprintf('%s/VERSION', $baseDir))),
    ];

    $response = new JsonResponse($jsonData);
    $response->addHeader('Cache-Control', 'no-store');
    $response->send();
} catch (Exception $e) {
    $response = new JsonResponse(['error' => $e->getMessage()], 500);
    $response->send();
}
