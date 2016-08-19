<?php
/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace fkooman\VPN\UserPortal;

use RuntimeException;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;

abstract class VpnApiClient
{
    /** @var \GuzzleHttp\Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    protected function exec($requestMethod, $requestUri, $options = array())
    {
        try {
            return $this->client->$requestMethod($requestUri, $options)->json();
        } catch (BadResponseException $e) {
            $responseBody = $e->getResponse()->json();

            if (array_key_exists('error_description', $responseBody)) {
                $errorMessage = sprintf('[%d] %s (%s)', $e->getResponse()->getStatusCode(), $responseBody['error'], $responseBody['error_description']);
            } else {
                $errorMessage = sprintf('[%d] %s', $e->getResponse()->getStatusCode(), $responseBody['error']);
            }

            throw new RuntimeException($errorMessage);
        }
    }
}
