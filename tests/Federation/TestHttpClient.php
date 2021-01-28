<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Federation;

use LC\Common\HttpClient\HttpClientInterface;
use LC\Common\HttpClient\HttpClientResponse;
use RuntimeException;

class TestHttpClient implements HttpClientInterface
{
    /**
     * @param string               $requestUrl
     * @param array<string,string> $queryParameters
     * @param array<string>        $requestHeaders
     *
     * @return \LC\Common\HttpClient\HttpClientResponse
     */
    public function get($requestUrl, array $queryParameters, array $requestHeaders = [])
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
     * @param string               $requestUrl
     * @param array<string,string> $queryParameters
     * @param array<string,string> $postData
     * @param array<string>        $requestHeaders
     *
     * @return HttpClientResponse
     */
    public function post($requestUrl, array $queryParameters, array $postData, array $requestHeaders = [])
    {
        throw new RuntimeException('"post" not implemented');
    }

    /**
     * @param string               $requestUrl
     * @param array<string,string> $queryParameters
     * @param string               $rawPost
     * @param array<string>        $requestHeaders
     *
     * @return HttpClientResponse
     */
    public function postRaw($requestUrl, array $queryParameters, $rawPost, array $requestHeaders = [])
    {
        throw new RuntimeException('"postJson" not implemented');
    }
}
