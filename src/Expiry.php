<?php

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
        $oneDayFromNow = clone $dateTime;
        $oneDayFromNow->add(new DateInterval('P1D'));

        // if we expire less than 7 days from now, keep as is
        if ($expiresAt < $oneDayFromNow) {
            return $sessionExpiry;
        }

        // expire in the middle of the night on the day of the expiry
        $nightExpiresAt = clone $expiresAt;
        $nightExpiresAt->modify('04:00');

        // if we are longer than sessionExpiry, subtract a day
        if ($nightExpiresAt > $expiresAt) {
            $nightExpiresAt->modify('yesterday 04:00');

            return $dateTime->diff($nightExpiresAt);
        }

        return $dateTime->diff($nightExpiresAt);
    }

    /**
     * @return \DateInterval
     */
    public static function doNotOutliveCa(DateTime $caExpiresAt, DateInterval $sessionExpiry, DateTime $dateTime = null)
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }
        $expiresAt = clone $dateTime;
        $expiresAt->add($sessionExpiry);

        if ($expiresAt < $caExpiresAt) {
            // we do not outlive the CA, great!
            return $sessionExpiry;
        }

        // return the interval between "now" and the moment the CA expires
        return $dateTime->diff($caExpiresAt);
    }
}
