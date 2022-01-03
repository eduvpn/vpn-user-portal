<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

class NullLogger implements LoggerInterface
{
    public function warning(string $logMessage): void
    {
        // NOP
    }

    public function error(string $logMessage): void
    {
        // NOP
    }

    public function notice(string $logMessage): void
    {
        // NOP
    }

    public function info(string $logMessage): void
    {
        // NOP
    }
}
