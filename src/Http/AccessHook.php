<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Http\Exception\HttpException;

/**
 * This hook is used to check if a user is allowed to access the portal/API.
 */
class AccessHook extends AbstractHook implements HookInterface
{
    /** @var array<string> */
    private array $accessPermissionList;

    /**
     * @param array<string> $accessPermissionList
     */
    public function __construct(array $accessPermissionList)
    {
        $this->accessPermissionList = $accessPermissionList;
    }

    public function afterAuth(Request $request, UserInfo &$userInfo): ?Response
    {
        if (!$this->hasPermissions($userInfo->permissionList())) {
            throw new HttpException('your account does not have the required permissions', 403);
        }

        return null;
    }

    /**
     * @param array<string> $userPermissionList
     */
    private function hasPermissions(array $userPermissionList): bool
    {
        foreach ($userPermissionList as $userPermission) {
            if (\in_array($userPermission, $this->accessPermissionList, true)) {
                return true;
            }
        }

        return false;
    }
}
