<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

class NullAuthenticationHook implements BeforeHookInterface
{
    private string $authUser;

    public function __construct(string $authUser)
    {
        $this->authUser = $authUser;
    }

    public function executeBefore(Request $request, array $hookData): UserInfo
    {
        return new UserInfo($this->authUser, []);
    }
}
