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

class Expiry
{
    /**
     * Calculate when a session will expire.
     *
     * The goal is to expire at 04:00 in the current timezone. in case the
     * expiry is set 1 week or longer. The current timezone is considerd as
     * per date.timezone PHP ini value.
     */
    public static function calculate(DateInterval $sessionExpiry, ?DateTimeImmutable $dateTime = null): DateInterval
    {
        if (null === $dateTime) {
            $dateTime = new DateTimeImmutable();
        }

        $expiresAt = $dateTime->add($sessionExpiry);

        // if we expire less than 7 days from now, keep as is
        if ($expiresAt < $dateTime->add(new DateInterval('P7D'))) {
            return $sessionExpiry;
        }

        // expire in the middle of the night on the day of the expiry
        $nightExpiresAt = $expiresAt->modify('02:00');

        // if we are longer than sessionExpiry, subtract a day
        if ($nightExpiresAt > $expiresAt) {
            return $dateTime->diff($nightExpiresAt->modify('yesterday 02:00'));
        }

        return $dateTime->diff($nightExpiresAt);
    }
}
