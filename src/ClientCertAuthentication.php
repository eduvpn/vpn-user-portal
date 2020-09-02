<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Request;
use LC\Common\Http\UserInfo;

class ClientCertAuthentication implements BeforeHookInterface
{
    /**
     * @return UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
    {
        return new UserInfo(
            $request->requireHeader('REMOTE_USER'),
            []
        );
    }
}
