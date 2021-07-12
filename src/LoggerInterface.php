<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

interface LoggerInterface
{
    const WARNING = 10;
    const ERROR = 20;
    const NOTICE = 30;
    const INFO = 40;

    public function warning(string $logMessage): void;

    public function error(string $logMessage): void;

    public function notice(string $logMessage): void;

    public function info(string $logMessage): void;
}
