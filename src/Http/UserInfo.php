<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

class UserInfo
{
    private const SESSION_EXPIRY_PREFIX = 'https://eduvpn.org/expiry#';

    private string $userId;

    private bool $isAdmin = false;

    /** @var array<string> */
    private array $permissionList;

    private ?string $authData;

    private bool $isDisabled;

    /**
     * @param array<string> $permissionList
     */
    public function __construct(string $userId, array $permissionList, ?string $authData = null, bool $isDisabled = false)
    {
        $this->userId = $userId;
        $this->permissionList = $permissionList;
        $this->authData = $authData;
        $this->isDisabled = $isDisabled;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function makeAdmin(): void
    {
        $this->isAdmin = true;
    }

    /**
     * Return the list of permissions for the user, both "full" and value.
     *
     * Format:
     *
     * SOURCE!CONTEXT!VALUE
     *
     * SOURCE = source where the permission arrived from.
     * - A = authentication source, e.g. LDAP, SAML, ...
     * - S = static permission source
     *
     * CONTEXT = the attribute the permission belonged to, e.g. `isMemberOf`
     * VALUE = the actual value of the permission, e.g. `student`
     *
     * @return array<string>
     */
    public function permissionList(): array
    {
        $permissionList = [];
        foreach ($this->permissionList as $permissionId) {
            // add the version with source and context
            $permissionList[] = $permissionId;
            $permissionParts = explode('!', $permissionId, 3);
            if (3 !== count($permissionParts)) {
                continue;
            }
            [,, $v] = $permissionParts;
            $permissionList[] = $v;
        }

        return array_values(array_unique($permissionList));
    }

    /**
     * Return the list of permissions for this user. The permissions DO
     * contain the source prefix and permission context.
     *
     * @return array<string>
     */
    public function rawPermissionList(): array
    {
        return array_values(array_unique($this->permissionList));
    }

    /**
     * @param array<string> $permissionList
     */
    public function setPermissionList(array $permissionList): void
    {
        $this->permissionList = $permissionList;
    }

    public function setAuthData(string $authData): void
    {
        $this->authData = $authData;
    }

    public function authData(): ?string
    {
        return $this->authData;
    }

    public function isDisabled(): bool
    {
        return $this->isDisabled;
    }

    /**
     * @return array<string>
     */
    public function sessionExpiry(): array
    {
        $sessionExpiryList = [];
        foreach ($this->permissionList() as $userPermission) {
            if (0 === strpos($userPermission, self::SESSION_EXPIRY_PREFIX)) {
                $sessionExpiryList[] = substr($userPermission, strlen(self::SESSION_EXPIRY_PREFIX));
            }
        }

        return $sessionExpiryList;
    }
}
