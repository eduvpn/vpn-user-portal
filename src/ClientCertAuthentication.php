<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\Request;
use LC\Common\Http\UserInfo;

class ClientCertAuthentication implements BeforeHookInterface
{
    /**
     * @return UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
    {
        if (null === $remoteUser = $request->optionalHeader('REMOTE_USER')) {
            throw new HttpException('client certificate authentication failed, no certificate provided', 400);
        }

        return new UserInfo($remoteUser, []);
    }
}
