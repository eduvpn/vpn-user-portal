<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Cfg;

use DateInterval;

class ApiConfig
{
    use ConfigTrait;

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    /**
     * OAuth "access_token" expiry.
     */
    public function tokenExpiry(): DateInterval
    {
        return new DateInterval($this->requireString('tokenExpiry', 'PT1H'));
    }

    public function maxActiveConfigurations(): int
    {
        return $this->requireInt('maxActiveConfigurations', 3);
    }
}
