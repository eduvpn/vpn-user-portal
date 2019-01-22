<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\HttpClient;

interface HttpClientInterface
{
    /**
     * @param string               $requestUri
     * @param array<string,string> $requestHeaders
     *
     * @return Response
     */
    public function get($requestUri, array $requestHeaders = []);
}
