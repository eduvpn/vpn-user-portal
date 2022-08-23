<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;


class UserInfo
{
    private string $userId;

    private bool $isAdmin = false;

    /** @var array<string> */
    //private array $permissionList;

    private ?string $authData;

    private array $settings;


    /**
     * @param array<string> $permissionList
     */
    public function __construct(string $userId, array $settings, ?string $authData = null)
    {
        $this->userId = $userId;
        //$this->permissionList = $settings['permissionList'];
        $this->authData = $authData;
        $this->settings = $settings;
        $this->settings['maxActiveConfigurations'] = @$settings['maxActiveConfigurations'] ?: '-1' ;
        $this->settings['maxActiveApiConfigurations'] = @$settings['maxActiveApiConfigurations'] ?: '-1';
        $this->settings['connectionExpiresAt'] = @$settings['connectionExpiresAt'] ?: null;
   
        
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function setAdmin(bool $isAdmin): void
    {
        $this->isAdmin = $isAdmin;
    }

    /**
     * @return array<string>
     */
    public function permissionList(): array
    {
        return $this->$settings['permissionList'];
    }

    public function authData(): ?string
    {
        return $this->authData;
    }

    public function settings(string $key): string
    {
        return $this->$settings[$key];
    }
}
