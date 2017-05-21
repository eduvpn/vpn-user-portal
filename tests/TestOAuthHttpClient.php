<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
