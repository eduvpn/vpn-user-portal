<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

use DateInterval;

class ApiConfig extends Config
{
    /**
     * @return \DateInterval
     */
    public function getTokenExpiry()
    {
        if (null === $configValue = $this->optionalString('tokenExpiry')) {
            return new DateInterval('PT1H');
        }

        return new DateInterval($configValue);
    }

    /**
     * @return bool
     */
    public function getRemoteAccess()
    {
        if (null === $configValue = $this->optionalBool('remoteAccess')) {
            return false;
        }

        return $configValue;
    }

    /**
     * @return array<string,array<string,string>>
     */
    public function getRemoteAccessList()
    {
        // XXX hmm we still need to put stuff here!
        return [];
    }
}
