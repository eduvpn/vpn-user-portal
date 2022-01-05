<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use Vpn\Portal\HttpClient\HttpClientInterface;
use Vpn\Portal\HttpClient\HttpClientRequest;
use Vpn\Portal\HttpClient\HttpClientResponse;

class TestHttpClient implements HttpClientInterface
{
    public function send(HttpClientRequest $httpClientRequest): HttpClientResponse
    {
        if ('http://localhost:41194/i/node' === $httpClientRequest->requestUrl()) {
            return new HttpClientResponse(
                200,
                '',
                '{"rel_load_average":[24,25,31],"load_average":[0.48,0.5,0.63],"cpu_count":2}'
            );
        }

        return new HttpClientResponse(
            404,
            '',
            'Not Found'
        );
    }
}
