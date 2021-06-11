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
