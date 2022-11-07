<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Json;

class JsonResponse extends Response
{
    /**
     * @param array<string,string> $responseHeaders
     */
    public function __construct(array $jsonData, array $responseHeaders = [], int $statusCode = 200)
    {
        $responseHeaders['Content-Type'] = 'application/json';
        parent::__construct(Json::encode($jsonData), $responseHeaders, $statusCode);
    }
}
