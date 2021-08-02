<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Federation;

use LC\Portal\HttpClient\HttpClientInterface;
use LC\Portal\HttpClient\HttpClientResponse;
use RuntimeException;

class TestHttpClient implements HttpClientInterface
{
    /**
     * @param array<string,string> $queryParameters
     * @param array<string>        $requestHeaders
     */
    public function get(string $requestUrl, array $queryParameters, array $requestHeaders = []): HttpClientResponse
    {
        switch ($requestUrl) {
            case 'https://disco.eduvpn.org/v2/server_list.json':
                return new HttpClientResponse(
                    200,
                    'Content-Type: application/json',
                    file_get_contents(sprintf('%s/data/server_list.json', __DIR__))
                );

            case 'https://disco.eduvpn.org/v2/server_list.json.minisig':
                return new HttpClientResponse(
                    200,
                    '',
                    file_get_contents(sprintf('%s/data/server_list.json.minisig', __DIR__))
                );

            case 'https://disco.eduvpn.org/v2/server_list_rollback.json':
                return new HttpClientResponse(
                    200,
                    'Content-Type: application/json',
                    file_get_contents(sprintf('%s/data/server_list_rollback.json', __DIR__))
                );

            case 'https://disco.eduvpn.org/v2/server_list_rollback.json.minisig':
                return new HttpClientResponse(
                    200,
                    '',
                    file_get_contents(sprintf('%s/data/server_list_rollback.json.minisig', __DIR__))
                );

            default:
                throw new RuntimeException('no such requestUrl');
        }
    }

    /**
     * @param array<string,string> $queryParameters
     * @param array<string,string> $postData
     * @param array<string>        $requestHeaders
     */
    public function post(string $requestUrl, array $queryParameters, array $postData, array $requestHeaders = []): HttpClientResponse
    {
        throw new RuntimeException('"post" not implemented');
    }

    /**
     * @param array<string,string> $queryParameters
     * @param array<string>        $requestHeaders
     */
    public function postRaw(string $requestUrl, array $queryParameters, string $rawPost, array $requestHeaders = []): HttpClientResponse
    {
        throw new RuntimeException('"postRaw" not implemented');
    }
}
