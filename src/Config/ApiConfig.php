<?php

declare(strict_types=1);

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
    public function getTokenExpiry(): DateInterval
    {
        if (null === $configValue = $this->optionalString('tokenExpiry')) {
            return new DateInterval('PT1H');
        }

        return new DateInterval($configValue);
    }

    public function getRemoteAccess(): bool
    {
        if (null === $configValue = $this->optionalBool('remoteAccess')) {
            return false;
        }

        return $configValue;
    }

    /**
     * @return array<string,array<string,string>>
     */
    public function getRemoteAccessList(): array
    {
        // XXX hmm we still need to put stuff here!
        return [];
    }
}
