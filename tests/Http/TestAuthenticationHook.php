<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\Http\BeforeHookInterface;
use LC\Portal\Http\Request;
use LC\Portal\Http\UserInfo;

class TestAuthenticationHook implements BeforeHookInterface
{
    /** @var string */
    private $authUser;

    /**
     * @param string $authUser
     */
    public function __construct($authUser)
    {
        $this->authUser = $authUser;
    }

    /**
     * @return UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
    {
        return new UserInfo($this->authUser, []);
    }
}
