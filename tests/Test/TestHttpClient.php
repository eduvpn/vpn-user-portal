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

namespace SURFnet\VPN\Portal\Test;

use SURFnet\VPN\Common\HttpClient\HttpClientInterface;
use RuntimeException;

class TestHttpClient implements HttpClientInterface
{
    public function get($requestUri, array $getData = [], array $requestHeaders = [])
    {
        switch ($requestUri) {
            case 'serverClient/instance_config':
                return self::wrap(
                    'instance_config',
                    [
                        'instanceNumber' => 1,
                        'vpnProfiles' => [
                            'internet' => [
                                'hideProfile' => false,
                                'enableAcl' => false,
                                'displayName' => 'Internet Access',
                                'twoFactor' => false,
                                'processCount' => 4,
                                'hostName' => 'vpn.example',
                            ],
                        ],
                    ]
                );
            case 'serverClient/server_profile?profile_id=internet':
                return self::wrap(
                    'server_profile',
                    [
                        'enableAcl' => false,
                        'displayName' => 'Internet Access',
                        'twoFactor' => false,
                        'processCount' => 4,
                        'hostName' => 'vpn.example',
                    ]
                );
            case 'serverClient/has_otp_secret?user_id=foo':
                return self::wrap('has_otp_secret', false);
            case 'caClient/user_certificate_list?user_id=foo':
                return self::wrap('user_certificate_list', [['name' => 'FooConfig', 'user_id' => 'foo', 'state' => 'V']]);
            case 'serverClient/user_groups?user_id=foo':
                return self::wrap('user_groups', []);
            case 'serverClient/disabled_common_names':
                return self::wrap('disabled_common_names', []);
            default:
                throw new RuntimeException(sprintf('unexpected requestUri "%s"', $requestUri));
        }
    }

    public function post($requestUri, array $postData, array $requestHeaders = [])
    {
        switch ($requestUri) {
            case 'caClient/add_client_certificate':
                return self::wrap(
                    'add_client_certificate',
                    [
                        'cn' => 'foo_MyConfig',
                        'valid_from' => 12345678,
                        'valid_to' => '23456789',
                        'ca' => 'CAPEM',
                        'cert' => 'CERTPEM',
                        'key' => 'KEYPEM',
                        'ta' => 'TAKEY',
                    ]
                );
            case 'serverClient/disable_common_name':
                return self::wrap('disable_common_name', true);
            case 'serverClient/kill_client':
                return self::wrap('kill_client', true);
            case 'serverClient/set_voot_token':
                return self::wrap('set_voot_token', true);
            default:
                throw new RuntimeException(sprintf('unexpected requestUri "%s"', $requestUri));
        }
    }

    private static function wrap($key, $response, $statusCode = 200)
    {
        return [
            $statusCode,
            [
                'data' => [
                    $key => $response,
                ],
            ],
        ];
    }
}
