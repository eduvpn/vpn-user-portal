<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

class HtmlResponse extends Response
{
    /**
     * @param array<string,string> $responseHeaders
     */
    public function __construct(string $responseBody, array $responseHeaders = [], int $statusCode = 200)
    {
        $responseHeaders['Content-Type'] = 'text/html;charset=utf-8';
        parent::__construct($responseBody, $responseHeaders, $statusCode);
    }
}
