<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

class UserInfo
{
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
}
