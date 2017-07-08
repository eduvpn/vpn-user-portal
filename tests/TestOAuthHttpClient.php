<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use fkooman\OAuth\Client\Http\HttpClientInterface;
use fkooman\OAuth\Client\Http\Request;
use fkooman\OAuth\Client\Http\Response;
use RuntimeException;

class TestOAuthHttpClient implements HttpClientInterface
{
    public function send(Request $request)
    {
        switch ($request->getUri()) {
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
            case 'https://example.org/token':
                return new Response(
                    200,
                    json_encode(
                        [
                            'access_token' => 'X',
                            'token_type' => 'bearer',
                        ]
                    ),
                    ['Content-Type' => 'application/json']
                );
            default:
                throw new RuntimeException('no such requestUri');
        }
    }
}
