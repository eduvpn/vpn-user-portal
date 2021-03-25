<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateTimeImmutable;

class UserInfo
{
    private string $userId;

    /** @var array<string> */
    private array $permissionList;

    private ?DateTimeImmutable $sessionExpiresAt = null;

    /**
     * @param array<string> $permissionList
     */
    public function __construct(string $userId, array $permissionList)
    {
        $this->userId = $userId;
        $this->permissionList = $permissionList;
    }

    public function setSessionExpiresAt(DateTimeImmutable $sessionExpiresAt): void
    {
        $this->sessionExpiresAt = $sessionExpiresAt;
    }

    public function getSessionExpiresAt(): ?DateTimeImmutable
    {
        return $this->sessionExpiresAt;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    /**
     * @return array<string>
     */
    public function getPermissionList(): array
    {
        return $this->permissionList;
    }
}
