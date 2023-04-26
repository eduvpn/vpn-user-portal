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
    private const ADMIN_PERMISSION = 'https://eduvpn.org/role/admin';

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

    /**
     * @return array<string>
     */
    public function permissionList(): array
    {
        return $this->permissionList;
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
        foreach ($this->permissionList as $userPermission) {
            if (0 === strpos($userPermission, self::SESSION_EXPIRY_PREFIX)) {
                $sessionExpiryList[] = substr($userPermission, strlen(self::SESSION_EXPIRY_PREFIX));
            }
        }

        return $sessionExpiryList;
    }

    /**
     * This method SHOULD NOT be used in the future because we'll use the role
     * based method to determine who is admin.
     */
    public function makeAdmin(): void
    {
        $this->isAdmin = true;
    }

    public function hasAdminRole(): bool
    {
        if ($this->isAdmin) {
            return true;
        }

        foreach ($this->permissionList as $userPermission) {
            if (self::ADMIN_PERMISSION === $userPermission) {
                return true;
            }
        }

        return false;
    }
}
