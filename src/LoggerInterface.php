<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

interface LoggerInterface
{
    public const WARNING = 10;
    public const ERROR = 20;
    public const NOTICE = 30;
    public const INFO = 40;

    public function warning(string $logMessage): void;

    public function error(string $logMessage): void;

    public function notice(string $logMessage): void;

    public function info(string $logMessage): void;
}
