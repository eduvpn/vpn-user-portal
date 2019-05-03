<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

class ProfileConfig extends Config
{
    public function __construct(array $configData)
    {
        parent::__construct($configData);
    }

    /**
     * @return array
     */
    public static function defaultConfig()
    {
        return [
            'defaultGateway' => true,
            'routes' => [],
            'dns' => ['9.9.9.9', '2620:fe::fe'],
            'clientToClient' => false,
            'listen' => '::',
            'enableLog' => false,
            'enableAcl' => false,
            'aclPermissionList' => [],
            'managementIp' => '127.0.0.1',
            'vpnProtoPorts' => [
                'udp/1194',
                'tcp/1194',
            ],
            'exposedVpnProtoPorts' => [],
            'hideProfile' => false,
            'tlsProtection' => 'tls-crypt',
            'blockLan' => true,
        ];
    }
}
