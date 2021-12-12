<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests\Http;

use Vpn\Portal\Http\Request;

class TestRequest extends Request
{
    public function __construct(array $serverData, array $getData = [], array $postData = [])
    {
        $serverData = array_merge(
            [
                'SERVER_NAME' => 'vpn.example',
                'SERVER_PORT' => '80',
                'REQUEST_SCHEME' => 'http',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SCRIPT_NAME' => '/index.php',
            ],
            $serverData
        );

        parent::__construct($serverData, $getData, $postData);
    }
}
