<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Exception\HttpException;

class NodeAuthenticationHook implements BeforeHookInterface
{
    private string $authToken;
    private string $authRealm;

    public function __construct(string $authToken, string $authRealm = 'Protected Area')
    {
        $this->authToken = $authToken;
        $this->authRealm = $authRealm;
    }

    public function executeBefore(Request $request, array $hookData): void
    {
        if (null === $authHeader = $request->optionalHeader('HTTP_AUTHORIZATION')) {
            throw new HttpException('no token', 401, ['WWW-Authenticate' => 'Bearer realm="'.$this->authRealm.'"']);
        }
        if (0 !== strpos($authHeader, 'Bearer ')) {
            throw new HttpException('invalid token type', 401, ['WWW-Authenticate' => 'Bearer realm="'.$this->authRealm.'"']);
        }
        $userAuthToken = substr($authHeader, 7);
        if (!\is_string($userAuthToken)) {
            throw new HttpException('malformed token', 401, ['WWW-Authenticate' => 'Bearer realm="'.$this->authRealm.'"']);
        }

        if (!hash_equals($this->authToken, $userAuthToken)) {
            throw new HttpException('invalid token', 401, ['WWW-Authenticate' => 'Bearer realm="'.$this->authRealm.'"']);
        }
    }
}
