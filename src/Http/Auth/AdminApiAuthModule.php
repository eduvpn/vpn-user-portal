<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use Vpn\Portal\FileIO;
use Vpn\Portal\Http\JsonResponse;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Response;
use Vpn\Portal\Http\UserInfo;
use Vpn\Portal\Validator;

class AdminApiAuthModule extends AbstractAuthModule
{
    private string $adminApiKeyFile;
    private string $authRealm;

    public function __construct(string $adminApiKeyFile, string $authRealm = 'Protected Area')
    {
        $this->adminApiKeyFile = $adminApiKeyFile;
        $this->authRealm = $authRealm;
    }

    public function userInfo(Request $request): ?UserInfo
    {
        if (null === $authHeader = $request->optionalHeader('HTTP_AUTHORIZATION')) {
            return null;
        }
        if (0 !== strpos($authHeader, 'Bearer ')) {
            return null;
        }
        $userAuthToken = substr($authHeader, 7);

        if (!hash_equals(FileIO::read($this->adminApiKeyFile), $userAuthToken)) {
            return null;
        }

        return new UserInfo(
            $request->requirePostParameter('user_id', fn (string $s) => Validator::userId($s)),
            []
        );
    }

    public function startAuth(Request $request): ?Response
    {
        return new JsonResponse(['error' => 'authentication required'], ['WWW-Authenticate' => 'Bearer realm="'.$this->authRealm.'"'], 401);
    }
}
