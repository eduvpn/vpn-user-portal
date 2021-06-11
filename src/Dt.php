<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Wrapper to deal with DateTime / DateTimeZone.
 */
class Dt
{
    public static function get(?string $dateTimeString = null): DateTimeImmutable
    {
        $dateTime = new DateTimeImmutable(null === $dateTimeString ? 'now' : $dateTimeString);

        // whatever the local time zone is, always convert to UTC
        return $dateTime->setTimeZone(new DateTimeZone('UTC'));
    }

    public static function atom(?string $dateTimeString = null): string
    {
        return self::get($dateTimeString)->format(DateTimeImmutable::ATOM);
    }
}
