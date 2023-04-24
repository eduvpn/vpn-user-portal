<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use DateInterval;
use DateTimeImmutable;

/**
 * Determine the "Session Expiry", i.e. when the OAuth authorization and
 * VPN configuration files expire and for the client to restart the
 * authorization process, or the user to return to the portal to obtain a new
 * configuration file.
 */
class Expiry
{
    private DateInterval $sessionExpiry;
    private DateTimeImmutable $dateTime;
    private DateTimeImmutable $caExpiresAt;

    public function __construct(DateInterval $sessionExpiry, DateTimeImmutable $dateTime, DateTimeImmutable $caExpiresAt)
    {
        $this->sessionExpiry = $sessionExpiry;
        $this->dateTime = $dateTime;
        $this->caExpiresAt = $caExpiresAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->clampToCa();
    }

    public function expiresIn(): DateInterval
    {
        return $this->dateTime->diff($this->clampToCa());
    }

    /**
     * Make sure that whatever sessionExpiry we have it never outlives the CA.
     */
    private function clampToCa(): DateTimeImmutable
    {
        $expiresAt = $this->dateTime->add($this->sessionExpiry);
        if ($expiresAt > $this->caExpiresAt) {
            return $this->caExpiresAt;
        }

        return $expiresAt;
    }
}
