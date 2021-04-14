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
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\UserInfoInterface;

class AbstractAuthModule implements AuthModuleInterface
{
    public function userInfo(Request $request): ?UserInfoInterface
    {
        return null;
    }

    public function startAuth(Request $request): ?Response
    {
        return null;
    }

    public function triggerLogout(Request $request): Response
    {
        throw new HttpException('logout not available for this authentication module', 400);
    }

    public function supportsLogout(): bool
    {
        return false;
    }
}
