<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';

use LC\Portal\Http\JsonResponse;
use LC\Portal\Http\Request;

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    $jsonData = [
        'api' => [
            'http://eduvpn.org/api#2' => [
                'api_base_uri' => $request->getRootUri().'api.php',
                'authorization_endpoint' => $request->getRootUri().'_oauth/authorize',
                'token_endpoint' => $request->getRootUri().'oauth.php/token',
            ],
        ],
    ];

    $response = new JsonResponse($jsonData);
    $response->send();
} catch (Exception $e) {
    $response = new JsonResponse(['error' => $e->getMessage()], 500);
    $response->send();
}
