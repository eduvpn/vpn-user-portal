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
    /** @var array<\Vpn\Portal\HttpClient\HttpClientRequest> */
    private array $logData = [];

    /**
     * @return array<\Vpn\Portal\HttpClient\HttpClientRequest>
     */
    public function logData(): array
    {
        return $this->logData;
    }

    public function send(HttpClientRequest $httpClientRequest): HttpClientResponse
    {
        $this->logData[] = $httpClientRequest;

        if ('http://localhost:41194/i/node' === $httpClientRequest->requestUrl()) {
            return new HttpClientResponse(
                200,
                '',
                '{"rel_load_average":[24,25,31],"load_average":[0.48,0.5,0.63],"cpu_count":2}'
            );
        }

        if ('http://localhost:41194/w/peer_list?show_all=yes' === $httpClientRequest->requestUrl()) {
            return new HttpClientResponse(
                200,
                '',
                '{"peer_list": []}'
            );
        }

        if ('http://localhost:41194/o/connection_list' === $httpClientRequest->requestUrl()) {
            return new HttpClientResponse(
                200,
                '',
                '{"connection_list": []}'
            );
        }

        return new HttpClientResponse(
            404,
            '',
            'Not Found'
        );
    }
}
