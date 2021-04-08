<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http\Auth;

use LC\Portal\Config;
use LC\Portal\Http\AuthModuleInterface;
use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\UserInfo;
use LC\Portal\Http\UserInfoInterface;

class ShibAuthModule implements AuthModuleInterface
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function userInfo(Request $request): ?UserInfoInterface
    {
        $userIdAttribute = $this->config->requireString('userIdAttribute');
        $permissionAttribute = $this->config->optionalString('permissionAttribute');

        $userPermissions = [];
        if (null !== $permissionAttribute) {
            $permissionHeaderValue = $request->optionalHeader($permissionAttribute);
            if (null !== $permissionHeaderValue) {
                $userPermissions = explode(';', $permissionHeaderValue);
            }
        }

        return new UserInfo(
            $request->requireHeader($userIdAttribute),
            $userPermissions
        );
    }

    public function startAuth(Request $request): ?Response
    {
        return null;
    }
}
