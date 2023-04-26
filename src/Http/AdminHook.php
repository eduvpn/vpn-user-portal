<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\TplInterface;

/**
 * Determines whether the current user is an "Admin", i.e. has extra
 * capabilities in the portal.
 *
 * It also augments the template engine with a boolean indicating whether the
 * user is "Admin", to show the admin portal options.
 *
 * Future versions would use the UserInfo object directly to determine whether
 * a user is an admin based on the permissions, either obtained through the IdM
 * or through the static permissions file. The only thing it would do is set
 * the template `isAdmin` key to true if the user is a designated admin.
 */
class AdminHook extends AbstractHook implements HookInterface
{
    /** @var array<string> */
    private array $adminPermissionList;

    /** @var array<string> */
    private array $adminUserIdList;

    private TplInterface $templateEngine;

    /**
     * @param array<string> $adminPermissionList
     * @param array<string> $adminUserIdList
     */
    public function __construct(array $adminPermissionList, array $adminUserIdList, TplInterface &$templateEngine)
    {
        $this->adminPermissionList = $adminPermissionList;
        $this->adminUserIdList = $adminUserIdList;
        $this->templateEngine = $templateEngine;
    }

    public function afterAuth(Request $request, UserInfo &$userInfo): ?Response
    {
        if ($this->isAdmin($userInfo)) {
            $userInfo->makeAdmin();
            $this->templateEngine->addDefault('isAdmin', true);
        }

        return null;
    }

    private function isAdmin(UserInfo $userInfo): bool
    {
        // we check whether the user is already a designated admin
        // because of the standardized admin role...
        if ($userInfo->hasAdminRole()) {
            return true;
        }

        if (\in_array($userInfo->userId(), $this->adminUserIdList, true)) {
            return true;
        }

        foreach ($userInfo->permissionList() as $userPermission) {
            if (\in_array($userPermission, $this->adminPermissionList, true)) {
                return true;
            }
        }

        return false;
    }
}
