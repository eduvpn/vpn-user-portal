<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
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

    public function isAdmin(UserInfo $userInfo): bool
    {
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

    public function afterAuth(Request $request, UserInfo $userInfo): ?Response
    {
        $this->templateEngine->addDefault('isAdmin', $this->isAdmin($userInfo));

        return null;
    }
}
