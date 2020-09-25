<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Config;
use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Request;
use LC\Common\Http\UserInfo;

class ShibAuthentication implements BeforeHookInterface
{
    /** @var \LC\Common\Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
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
}
