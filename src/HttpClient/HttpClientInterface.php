<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\HttpClient;

interface HttpClientInterface
{
    /**
     * @param array<string,string> $queryParameters
     * @param array<string>        $requestHeaders
     */
    public function get(string $requestUrl, array $queryParameters = [], array $requestHeaders = []): HttpClientResponse;

    /**
     * @param array<string,string>               $queryParameters
     * @param array<string,array<string>|string> $postParameters
     * @param array<string>                      $requestHeaders
     */
    public function post(string $requestUrl, array $queryParameters = [], array $postParameters = [], array $requestHeaders = []): HttpClientResponse;
}
