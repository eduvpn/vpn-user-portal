<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OAuth;

use fkooman\OAuth\Server\AccessToken;

/**
 * XXX can we somehow only use AccessToken? Do we need "isLocal" still?
 */
class VpnAccessToken
{
    private AccessToken $accessToken;
    private bool $isLocal;

    public function __construct(AccessToken $accessToken, bool $isLocal)
    {
        $this->accessToken = $accessToken;
        $this->isLocal = $isLocal;
    }

    public function accessToken(): AccessToken
    {
        return $this->accessToken;
    }

    public function isLocal(): bool
    {
        return $this->isLocal;
    }

    public function getUserId(): string
    {
        return $this->accessToken()->userId();
    }

    /**
     * XXX rename this one to permissionList().
     */
    public function getPermissionList(): array
    {
        return [];
    }
}
