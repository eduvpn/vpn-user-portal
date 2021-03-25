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
use LC\Portal\Http\Request;
use LC\Portal\Http\UserInfo;

class ShibAuthentication implements BeforeHookInterface
{
    /** @var \LC\Portal\Config */
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
