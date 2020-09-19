<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Federation;

use LC\Portal\Federation\HttpClientInterface;
use LC\Portal\Federation\HttpClientResponse;
use RuntimeException;

class TestHttpClient implements HttpClientInterface
{
    /**
     * @param string                $requestUri
     * @param array<string, string> $requestHeaders
     *
     * @return Response
     */
    public function get($requestUri, array $requestHeaders = [])
    {
        switch ($requestUri) {
            case 'https://disco.eduvpn.org/v2/server_list.json':
                return new HttpClientResponse(
                    200,
                    ['Content-Type' => 'application/json'],
                    file_get_contents(sprintf('%s/data/server_list.json', __DIR__))
                );
            case 'https://disco.eduvpn.org/v2/server_list.json.minisig':
                return new HttpClientResponse(
                    200,
                    ['Content-Type' => 'foo/bar'],
                    file_get_contents(sprintf('%s/data/server_list.json.minisig', __DIR__))
                );
            default:
                throw new RuntimeException('no such requestUri');
        }
    }
}
