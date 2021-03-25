<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

class NullAuthenticationHook implements BeforeHookInterface
{
    /** @var string */
    private $authUser;

    public function __construct(string $authUser)
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
