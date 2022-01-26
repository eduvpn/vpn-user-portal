<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use Vpn\Portal\Binary;
use Vpn\Portal\FileIO;
use Vpn\Portal\Http\JsonResponse;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Response;
use Vpn\Portal\Http\UserInfo;
use Vpn\Portal\Validator;

class NodeAuthModule extends AbstractAuthModule
{
    private string $baseDir;
    private string $authRealm;

    public function __construct(string $baseDir, string $authRealm = 'Protected Area')
    {
        $this->baseDir = $baseDir;
        $this->authRealm = $authRealm;
    }

    public function userInfo(Request $request): ?UserInfo
    {
        if (null === $nodeNumber = $request->optionalHeader('HTTP_X_NODE_NUMBER')) {
            return null;
        }
        // make sure nodeNumber is int >= 0
        Validator::nodeNumber($nodeNumber);

        if (null === $authHeader = $request->optionalHeader('HTTP_AUTHORIZATION')) {
            return null;
        }
        if (0 !== strpos($authHeader, 'Bearer ')) {
            return null;
        }
        $userAuthToken = Binary::safeSubstr($authHeader, 7);

        // get the node key for this node number
        $nodeKeyFile = sprintf('%s/config/keys/node.%d.key', $this->baseDir, $nodeNumber);
        if (!FileIO::exists($nodeKeyFile)) {
            return null;
        }

        if (!hash_equals(FileIO::read($nodeKeyFile), $userAuthToken)) {
            return null;
        }

        return new UserInfo($nodeNumber, []);
    }

    public function startAuth(Request $request): ?Response
    {
        return new JsonResponse(['error' => 'authentication required'], ['WWW-Authenticate' => 'Bearer realm="'.$this->authRealm.'"'], 401);
    }
}
