<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http\Auth;

use LC\Portal\Http\AuthModuleInterface;
use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\UserInfo;
use LC\Portal\Http\UserInfoInterface;

class NodeAuthModule implements AuthModuleInterface
{
    private string $authToken;
    private string $authRealm;

    public function __construct(string $authToken, string $authRealm = 'Protected Area')
    {
        $this->authToken = $authToken;
        $this->authRealm = $authRealm;
    }

    public function userInfo(Request $request): ?UserInfoInterface
    {
        if (null === $authHeader = $request->optionalHeader('HTTP_AUTHORIZATION')) {
            return null;
        }
        if (0 !== strpos($authHeader, 'Bearer ')) {
            return null;
        }
        $userAuthToken = substr($authHeader, 7);
        if (!\is_string($userAuthToken)) {
            return null;
        }

        if (!hash_equals($this->authToken, $userAuthToken)) {
            return null;
        }

        return new UserInfo('vpn-server-node', []);
    }

    public function startAuth(Request $request): ?Response
    {
        $authResponse = new Response(401);
        $authResponse->addHeader('WWW-Authenticate', 'Bearer realm="'.$this->authRealm.'"');

        return $authResponse;
    }
}
