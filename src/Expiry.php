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
use DateTimeImmutable;
use DateTimeZone;

class Expiry
{
    /**
     * Calculate when a session will expire.
     *
     * The goal is to expire "sessions" at 04:00 in the current timezone, iff
     * the expiry is set 1 week or longer. The current timezone is considered
     * as per date.timezone PHP ini value.
     *
     * The expiry is always bound by the time the CA expires.
     */
    public static function calculate(DateInterval $sessionExpiry, DateTimeImmutable $caExpiresAt, ?DateTimeImmutable $dateTime = null): DateInterval
    {
        if (null === $dateTime) {
            $dateTime = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        $expiresAt = $dateTime->add($sessionExpiry);

        // if we expire less than 7 days from now, keep as is
        if ($expiresAt < $dateTime->add(new DateInterval('P7D'))) {
            // but upperbound is still CA expiry
            if ($expiresAt > $caExpiresAt) {
                return $dateTime->diff($caExpiresAt);
            }

            return $sessionExpiry;
        }

        // expire at 04:00 in the night on the day of the expiry
        $nightExpiresAt = $expiresAt->modify('04:00');

        // if we end up longer than sessionExpiry, because it is between 00:00
        // and 04:00, subtract a day
        if ($nightExpiresAt > $expiresAt) {
            return $dateTime->diff($nightExpiresAt->modify('yesterday 04:00'));
        }

        // CA expiry is upper bound in all cases
        if ($nightExpiresAt > $caExpiresAt) {
            $nightExpiresAt = $caExpiresAt;
        }

        return $dateTime->diff($nightExpiresAt);
    }
}
