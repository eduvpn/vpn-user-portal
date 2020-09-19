<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Federation;

interface HttpClientInterface
{
    /**
     * @param string        $requestUrl
     * @param array<string> $requestHeaders
     *
     * @return HttpClientResponse
     */
    public function get($requestUrl, array $requestHeaders);
}
