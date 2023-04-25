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
use Vpn\Portal\Http\UserInfo;

/**
 * Determine the "Session Expiry", i.e. when the OAuth authorization and
 * VPN configuration files expire and for the client to restart the
 * authorization process, or the user to return to the portal to obtain a new
 * configuration file.
 */
class Expiry
{
    private DateInterval $defaultSessionExpiry;

    /** @var array<string> */
    private array $supportedSessionExpiry;

    private DateTimeImmutable $dateTime;

    private DateTimeImmutable $caExpiresAt;

    /**
     * @param array<string> $supportedSessionExpiry
     */
    public function __construct(DateInterval $defaultSessionExpiry, array $supportedSessionExpiry, DateTimeImmutable $dateTime, DateTimeImmutable $caExpiresAt)
    {
        $this->defaultSessionExpiry = $defaultSessionExpiry;
        $this->supportedSessionExpiry = $supportedSessionExpiry;
        $this->dateTime = $dateTime;
        $this->caExpiresAt = $caExpiresAt;
    }

    public function expiresAt(?UserInfo $userInfo = null): DateTimeImmutable
    {
        if(null !== $userInfo) {
            $userSessionExpiry = $userInfo->sessionExpiry();
            if(1 === count($userSessionExpiry)) {
                if(in_array($userSessionExpiry[0], $this->supportedSessionExpiry, true)) {
                    return $this->clampToCa(new DateInterval($userSessionExpiry[0]));
                }
            }
        }

        return $this->clampToCa($this->defaultSessionExpiry);
    }

    public function expiresIn(?UserInfo $userInfo = null): DateInterval
    {
        return $this->dateTime->diff($this->expiresAt($userInfo));
    }

    /**
     * Make sure that whatever sessionExpiry we have, it never outlives the CA.
     */
    private function clampToCa(DateInterval $sessionExpiry): DateTimeImmutable
    {
        $expiresAt = $this->dateTime->add($sessionExpiry);
        if ($expiresAt > $this->caExpiresAt) {
            return $this->caExpiresAt;
        }

        return $expiresAt;
    }
}
