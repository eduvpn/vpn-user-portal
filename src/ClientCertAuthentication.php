<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\Http\BeforeHookInterface;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\Http\Request;
use LC\Portal\Http\UserInfo;

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
