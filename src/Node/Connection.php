<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Node;

use DateTime;

class Connection
{
    /** @var NodeApiInterface */
    private $nodeApi;

    public function __construct(NodeApiInterface $nodeApi)
    {
        $this->nodeApi = $nodeApi;
    }

    /**
     * @return void
     */
    public function connect(array $envData)
    {
        $this->nodeApi->connect(
            $envData['PROFILE_ID'],
            $envData['common_name'],
            $envData['ifconfig_pool_remote_ip'],
            $envData['ifconfig_pool_remote_ip6'],
            new DateTime(sprintf('@%d', $envData['time_unix']))
        );
    }

    /**
     * @return void
     */
    public function disconnect(array $envData)
    {
        $this->nodeApi->disconnect(
            $envData['PROFILE_ID'],
            $envData['common_name'],
            $envData['ifconfig_pool_remote_ip'],
            $envData['ifconfig_pool_remote_ip6'],
            new DateTime(sprintf('@%d', $envData['time_unix'])),
            new DateTime(sprintf('@%d', $envData['time_unix'] + $envData['time_duration'])),
            $envData['bytes_received'] + $envData['bytes_sent'],
        );
    }
}
