<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use fkooman\OAuth\Server\AccessToken;

class ApiUserInfo
{
    private string $userId;

    /** @var array<string> */
    private array $permissionList;

    private ?string $authData;

    private bool $isDisabled;

    private AccessToken $accessToken;

    /**
     * @param array<string> $permissionList
     */
    public function __construct(string $userId, array $permissionList, AccessToken $accessToken, ?string $authData = null, bool $isDisabled = false)
    {
        $this->userId = $userId;
        $this->permissionList = $permissionList;
        $this->accessToken = $accessToken;
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

    public function accessToken(): AccessToken
    {
        return $this->accessToken;
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
