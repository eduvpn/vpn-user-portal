<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\TplInterface;

/**
 * Augments the "template" with information about whether or not the user is
 * an "admin", i.e. should see the admin menu items.
 */
class AdminHook extends AbstractHook implements BeforeHookInterface
{
    /** @var array<string> */
    private array $adminPermissionList;

    /** @var array<string> */
    private array $adminUserIdList;

    private TplInterface $tpl;

    /**
     * @param array<string> $adminPermissionList
     * @param array<string> $adminUserIdList
     */
    public function __construct(array $adminPermissionList, array $adminUserIdList, TplInterface &$tpl)
    {
        $this->adminPermissionList = $adminPermissionList;
        $this->adminUserIdList = $adminUserIdList;
        $this->tpl = $tpl;
    }

    public function isAdmin(UserInfo $userInfo): bool
    {
        if (\in_array($userInfo->userId(), $this->adminUserIdList, true)) {
            return true;
        }

        $userPermissionList = $userInfo->permissionList();
        foreach ($userPermissionList as $userPermission) {
            if (\in_array($userPermission, $this->adminPermissionList, true)) {
                return true;
            }
        }

        return false;
    }

    public function afterAuth(UserInfo $userInfo, Request $request): ?Response
    {
        $this->tpl->addDefault(
            [
                'isAdmin' => $this->isAdmin($userInfo),
            ]
        );

        return null;
    }
}
