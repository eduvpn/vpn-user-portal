<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use RuntimeException;

class SysLogger implements LoggerInterface
{
    public function __construct(string $appName)
    {
        if (false === openlog($appName, LOG_PERROR | LOG_ODELAY, LOG_USER)) {
            throw new RuntimeException('unable to open syslog');
        }
    }

    public function __destruct()
    {
        closelog();
    }

    public function warning(string $logMessage): void
    {
        syslog(LOG_WARNING, $logMessage);
    }

    public function error(string $logMessage): void
    {
        syslog(LOG_ERR, $logMessage);
    }

    public function info(string $logMessage): void
    {
        syslog(LOG_INFO, $logMessage);
    }
}
