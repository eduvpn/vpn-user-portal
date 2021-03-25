<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;

class Logger extends AbstractLogger implements LoggerInterface
{
    /**
     * @param string $ident
     */
    public function __construct($ident)
    {
        if (false === openlog($ident, \LOG_PERROR | \LOG_ODELAY, \LOG_USER)) {
            throw new RuntimeException('unable to open syslog');
        }
    }

    public function __destruct()
    {
        closelog();
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

    /**
     * @param int $level
     *
     * @return int
     */
    private static function levelToPriority($level)
    {
        switch ($level) {
            case LogLevel::EMERGENCY:
                return \LOG_EMERG;
            case LogLevel::ALERT:
                return \LOG_ALERT;
            case LogLevel::CRITICAL:
                return \LOG_CRIT;
            case LogLevel::ERROR:
                return \LOG_ERR;
            case LogLevel::WARNING:
                return \LOG_WARNING;
            case LogLevel::NOTICE:
                return \LOG_NOTICE;
            case LogLevel::INFO:
                return \LOG_INFO;
            case LogLevel::DEBUG:
                return \LOG_DEBUG;
            default:
                throw new RuntimeException('unknown log level');
        }
    }
}
