<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateInterval;
use DateTime;

class Expiry
{
    /**
     * Calculate when a session will expire.
     *
     * The goal is to expire at 04:00 in the current timezone. in case the
     * expiry is set 1 week or longer. The current timezone is considerd as
     * per date.timezone PHP ini value.
     *
     * @return \DateInterval
     */
    public static function calculate(DateInterval $sessionExpiry, DateTime $dateTime = null)
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }
        $expiresAt = clone $dateTime;
        $expiresAt->add($sessionExpiry);
        $sevenDaysFromNow = clone $dateTime;
        $sevenDaysFromNow->add(new DateInterval('P7D'));

        // if we expire less than 7 days from now, keep as is
        if ($expiresAt < $sevenDaysFromNow) {
            return $sessionExpiry;
        }

        // expire in the middle of the night on the day of the expiry
        $nightExpiresAt = clone $expiresAt;
        $nightExpiresAt->modify('02:00');

        // if we are longer than sessionExpiry, subtract a day
        if ($nightExpiresAt > $expiresAt) {
            $nightExpiresAt->modify('yesterday 02:00');

            return $dateTime->diff($nightExpiresAt);
        }

        return $dateTime->diff($nightExpiresAt);
    }
}
