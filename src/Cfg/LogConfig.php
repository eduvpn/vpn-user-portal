<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Cfg;

class LogConfig
{
    use ConfigTrait;

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    public function syslogConnectionEvents(): bool
    {
        return $this->requireBool('syslogConnectionEvents', false);
    }

    public function originatingIp(): bool
    {
        return $this->requireBool('originatingIp', false);
    }

    /**
     * Also write the `auth_data` from the `users` table of the database to
     * syslog.
     */
    public function authData(): bool
    {
        return $this->requireBool('authData', false);
    }

}
