<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use DateInterval;
use DateTimeImmutable;

class Expiry
{
    public static function calculate(DateTimeImmutable $dateTime, DateTimeImmutable $caExpiresAt, DateInterval $sessionExpiry): DateInterval
    {
        $expiresAt = $dateTime->add($sessionExpiry);

        // make sure we never expire after the CA
        if ($expiresAt > $caExpiresAt) {
            return $dateTime->diff($caExpiresAt);
        }

        return $sessionExpiry;
    }
}
