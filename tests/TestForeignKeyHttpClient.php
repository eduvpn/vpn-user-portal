<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\Tests;

use LetsConnect\Portal\HttpClient\HttpClientInterface;
use LetsConnect\Portal\HttpClient\Response;
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
            case 'https://example.org/federation.json':
                return new Response(
                    200,
                    file_get_contents(sprintf('%s/data/federation.json', __DIR__)),
                    ['Content-Type' => 'application/json']
                );
            case 'https://example.org/federation.json.sig':
            case 'https://example.org/federation.json.wrong.sig':
                return new Response(
                    200,
                    file_get_contents(sprintf('%s/data/federation.json.sig', __DIR__)),
                    ['Content-Type' => 'application/json']
                );
            case 'https://example.org/federation.json.wrong':
                return new Response(
                    200,
                    file_get_contents(sprintf('%s/data/federation.json.wrong', __DIR__)),
                    ['Content-Type' => 'application/json']
                );
            default:
                throw new RuntimeException('no such requestUri');
        }
    }
}
