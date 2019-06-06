<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\OpenVpn\ServerManagerInterface;

class TestServerManager implements ServerManagerInterface
{
    /**
     * @return array<string,array>
     */
    public function connections()
    {
        return [
            'default' => [
                [
                    'common_name' => 'CN_1',
                    'virtual_address' => [
                        '10.0.0.2',
                        'fd00::1000',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param string $commonName
     *
     * @return int
     */
    public function kill($commonName)
    {
        return 0;
    }
}
