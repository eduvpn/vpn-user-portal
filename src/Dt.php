<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Wrapper to deal with DateTime / DateTimeZone.
 */
class Dt
{
    public static function get(?string $dateTimeString = null, ?DateTimeZone $dateTimeZone = null): DateTimeImmutable
    {
        $dateTime = new DateTimeImmutable(null === $dateTimeString ? 'now' : $dateTimeString, $dateTimeZone);

        // whatever the local time zone is, always convert to UTC
        return $dateTime->setTimeZone(new DateTimeZone('UTC'));
    }
}
