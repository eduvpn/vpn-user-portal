<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\Http\Auth\LdapCredentialValidator;
use Vpn\Portal\Http\Exception\HttpException;

class LdapConnectionHook implements ConnectionHookInterface
{
    private LdapCredentialValidator $ldapCredentialValidator;

    public function __construct(LdapCredentialValidator $ldapCredentialValidator)
    {
        $this->ldapCredentialValidator = $ldapCredentialValidator;
    }

    public function connect(string $userId, string $profileId, string $connectionId, string $ipFour, string $ipSix, ?string $originatingIp): void
    {
        if (!$this->ldapCredentialValidator->userExists($userId)) {
            throw new HttpException('user account no longer exists', 403);
        }
    }

    public function disconnect(string $userId, string $profileId, string $connectionId, string $ipFour, string $ipSix): void
    {
        // NOP
    }
}
