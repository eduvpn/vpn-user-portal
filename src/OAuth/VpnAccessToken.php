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
use LC\Portal\Http\UserInfoInterface;

class VpnAccessToken implements UserInfoInterface
{
    private AccessToken $accessToken;
    private bool $isLocal;

    public function __construct(AccessToken $accessToken, bool $isLocal)
    {
        $this->accessToken = $accessToken;
        $this->isLocal = $isLocal;
    }

    public function clientId(): string
    {
        return $this->accessToken->clientId();
    }

    public function getUserId(): string
    {
        return $this->accessToken->userId();
    }

    public function isLocal(): bool
    {
        return $this->isLocal;
    }

    public function getPermissionList(): array
    {
        return [];
    }
}
