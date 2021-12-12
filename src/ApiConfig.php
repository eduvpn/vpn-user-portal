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

class ApiConfig
{
    use ConfigTrait;

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    /**
     * OAuth "access_token" expiry.
     */
    public function tokenExpiry(): DateInterval
    {
        return new DateInterval($this->requireString('tokenExpiry', 'PT1H'));
    }

    /**
     * Maximum number of OAuth client authorizations (per user).
     */
    public function maxNumberOfAuthorizedClients(): int
    {
        // XXX maybe this is not good, at least not until it is very easy to
        // immediately revoke a client from the error page...
        return $this->requireInt('maxNumberOfAuthorizedClients', 15);
    }
}
