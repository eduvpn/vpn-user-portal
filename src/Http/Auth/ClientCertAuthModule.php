<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http\Auth;

use LC\Portal\Http\Exception\HttpException;
use LC\Portal\Http\Request;
use LC\Portal\Http\UserInfo;

class ClientCertAuthModule extends AbstractAuthModule
{
    public function userInfo(Request $request): ?UserInfo
    {
        if (null === $remoteUser = $request->optionalHeader('REMOTE_USER')) {
            throw new HttpException('client certificate authentication failed, no certificate provided', 400);
        }

        return new UserInfo($remoteUser, []);
    }
}
