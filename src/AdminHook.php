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
use LC\Portal\Http\Service;

/**
 * Augments the "template" with information about whether or not the user is
 * an "admin", i.e. should see the admin menu items.
 */
class AdminHook implements BeforeHookInterface
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

    public function executeBefore(Request $request, array $hookData): bool
    {
        $whiteList = [
            'POST' => [
                '/_form/auth/verify',
                '/_form/auth/logout',   // DEPRECATED
                '/_logout',
            ],
        ];
        if (Service::isWhitelisted($request, $whiteList)) {
            return false;
        }

        if (!\array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }
        /** @var \LC\Portal\Http\UserInfo */
        $userInfo = $hookData['auth'];

        // is the userId listed in the adminUserIdList?
        if (\in_array($userInfo->getUserId(), $this->adminUserIdList, true)) {
            $this->tpl->addDefault(['isAdmin' => true]);

            return true;
        }

        // is any of the user's permissions listed in adminPermissionList?
        $userPermissionList = $userInfo->getPermissionList();
        foreach ($userPermissionList as $userPermission) {
            if (\in_array($userPermission, $this->adminPermissionList, true)) {
                $this->tpl->addDefault(['isAdmin' => true]);

                return true;
            }
        }

        $this->tpl->addDefault(['isAdmin' => false]);

        return false;
    }
}
