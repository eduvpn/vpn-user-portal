<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\HttpClient\HttpClientInterface;
use LC\Portal\HttpClient\Response;
use RuntimeException;

class TestForeignKeyHttpClient implements HttpClientInterface
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
                return new Response(
                    200,
                    file_get_contents(sprintf('%s/data/server_list.json', __DIR__)),
                    ['Content-Type' => 'application/json']
                );
            case 'https://disco.eduvpn.org/v2/server_list.json.minisig':
                return new Response(
                    200,
                    file_get_contents(sprintf('%s/data/server_list.json.minisig', __DIR__)),
                    ['Content-Type' => 'foo/bar']
                );
            default:
                throw new RuntimeException('no such requestUri');
        }
    }
}
