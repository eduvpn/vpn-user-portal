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

use RuntimeException;
use SURFnet\VPN\Common\HttpClient\HttpClientInterface;

class TestHttpClient implements HttpClientInterface
{
    public function get($requestUri, array $getData = [], array $requestHeaders = [])
    {
        switch ($requestUri) {
            case 'serverClient/profile_list':
                return self::wrap(
                    'profile_list',
                    [
                        'internet' => [
                            'hideProfile' => false,
                            'enableAcl' => false,
                            'displayName' => 'Internet Access',
                            'twoFactor' => false,
                            'tlsCrypt' => false,
                            'vpnProtoPorts' => [
                                'udp/1194',
                                'udp/1195',
                                'tcp/1194',
                                'tcp/443',
                            ],
                            'hostName' => 'vpn.example',
                        ],
                    ]
                );
            case 'serverClient/client_certificate_list?user_id=foo':
                return self::wrap(
                    'client_certificate_list',
                    [
                        [
                            'display_name' => 'Foo',
                            'valid_from' => 123456,
                            'valid_to' => 2345567,
                        ],
                    ]
                );
            case 'serverClient/user_messages?user_id=foo':
                return self::wrap('user_messages', []);
            case 'serverClient/system_messages?message_type=motd':
                return self::wrap('system_messages', [['id' => 1, 'message_type' => 'motd', 'message_body' => 'Hello World!']]);
//            case 'serverClient/has_yubi_key_id?user_id=foo':
//                return self::wrap('has_yubi_key_id', false);
            case 'serverClient/yubi_key_id?user_id=foo':
                return self::wrap('yubi_key_id', false);
            case 'serverClient/has_totp_secret?user_id=foo':
                return self::wrap('has_totp_secret', false);
            case 'serverClient/user_groups?user_id=foo':
                return self::wrap('user_groups', []);
            case 'serverClient/client_certificate_info?common_name=12345678901234567890123456789012':
                return self::wrap('client_certificate_info', ['display_name' => 'Foo']);
            case 'serverClient/server_info':
                return self::wrap(
                    'server_info',
                    [
                        'ca' => 'CAPEM',
                        'ta' => 'TAKEY',
                    ]
                );
            default:
                throw new RuntimeException(sprintf('unexpected requestUri "%s"', $requestUri));
        }
    }

    public function post($requestUri, array $postData = [], array $requestHeaders = [])
    {
        switch ($requestUri) {
            case 'serverClient/add_client_certificate':
                return self::wrap(
                    'add_client_certificate',
                    [
                        'valid_from' => 12345678,
                        'valid_to' => '23456789',
                        'certificate' => 'CERTPEM',
                        'private_key' => 'KEYPEM',
                    ]
                );
            case 'serverClient/delete_client_certificate':
                return self::wrap('delete_client_certificate', true);
            case 'serverClient/kill_client':
                return self::wrap('kill_client', true);
            case 'serverClient/set_voot_token':
                return self::wrap('set_voot_token', true);
            default:
                throw new RuntimeException(sprintf('unexpected requestUri "%s"', $requestUri));
        }
    }

    private static function wrap($key, $responseData, $statusCode = 200)
    {
        return [
            $statusCode,
            [
                $key => [
                    'ok' => true,
                    'data' => $responseData,
                ],
            ],
        ];
    }

    private static function wrapError($key, $errorMessage, $statusCode = 200)
    {
        return [
            $statusCode,
            [
                $key => [
                    'ok' => false,
                    'error' => $errorMessage,
                ],
            ],
        ];
    }
}
