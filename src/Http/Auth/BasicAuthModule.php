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

class BasicAuthModule implements AuthModuleInterface
{
    /** @var array<string,string> */
    private $authUserList;

    /**
     * @param array<string,string> $authUserList
     */
    public function __construct(array $authUserList)
    {
        $this->authUserList = $authUserList;
    }

    public function userInfo(Request $request): ?UserInfoInterface
    {
        if (null === $authUser = $request->optionalHeader('PHP_AUTH_USER')) {
            return null;
        }
        if (null === $authPass = $request->optionalHeader('PHP_AUTH_PW')) {
            return null;
        }

        if (!\array_key_exists($authUser, $this->authUserList)) {
            return null;
        }

        if (!hash_equals($this->authUserList[$authUser], $authPass)) {
            return null;
        }

        return new UserInfo($authUser, []);
    }

    public function startAuth(Request $request): ?Response
    {
        $httpResponse = new Response(401, 'text/html');
        $httpResponse->setBody('authentication required');
        $httpResponse->addHeader('WWW-Authenticate', 'Basic realm="VPN Portal"');

        return $httpResponse;
    }
}
