<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use RuntimeException;

class Logger extends AbstractLogger
{
    public function __construct(string $ident)
    {
        if (false === openlog($ident, LOG_PERROR | LOG_ODELAY, LOG_USER)) {
            throw new RuntimeException('unable to open syslog');
        }
    }

    public function __destruct()
    {
        if (false === closelog()) {
            throw new RuntimeException('unable to close syslog');
        }
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     */
    public function log($level, $message, array $context = []): void
    {
        // convert level to syslog level
        $syslogPriority = self::levelToPriority($level);

        syslog(
            $syslogPriority,
            $message
        );
    }

    private static function levelToPriority(int $level): int
    {
        switch ($level) {
            case LogLevel::EMERGENCY:
                return LOG_EMERG;
            case LogLevel::ALERT:
                return LOG_ALERT;
            case LogLevel::CRITICAL:
                return LOG_CRIT;
            case LogLevel::ERROR:
                return LOG_ERR;
            case LogLevel::WARNING:
                return LOG_WARNING;
            case LogLevel::NOTICE:
                return LOG_NOTICE;
            case LogLevel::INFO:
                return LOG_INFO;
            case LogLevel::DEBUG:
                return LOG_DEBUG;
            default:
                throw new RuntimeException('unknown log level');
        }
    }
}
