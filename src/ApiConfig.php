<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateInterval;

class ApiConfig
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function remoteAccess(): bool
    {
        return $this->config->requireBool('remoteAccess', false);
    }

    public function tokenExpiry(): DateInterval
    {
        return new DateInterval($this->config->requireString('tokenExpiry', 'PT1H'));
    }
}
