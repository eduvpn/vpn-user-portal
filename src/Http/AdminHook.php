<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Exception\HttpException;
use LC\Portal\TplInterface;

/**
 * Augments the "template" with information about whether or not the user is
 * an "admin", i.e. should see the admin menu items.
 */
class AdminHook implements BeforeHookInterface
{
    /** @var array<string> */
    private $adminPermissionList;

    /** @var array<string> */
    private $adminUserIdList;

    /** @var \LC\Portal\TplInterface */
    private $tpl;

    /**
     * @param array<string>           $adminPermissionList
     * @param array<string>           $adminUserIdList
     * @param \LC\Portal\TplInterface $tpl
     */
    public function __construct(array $adminPermissionList, array $adminUserIdList, TplInterface &$tpl)
    {
        $this->adminPermissionList = $adminPermissionList;
        $this->adminUserIdList = $adminUserIdList;
        $this->tpl = $tpl;
    }

    /**
     * @param Request $request
     * @param array   $hookData
     *
     * @return bool
     */
    public function executeBefore(Request $request, array $hookData)
    {
        $whiteList = [
            'POST' => [
                '/_saml/acs',
                '/_form/auth/verify',
                '/_form/auth/logout',   // DEPRECATED
                '/_logout',
            ],
            'GET' => [
                '/_saml/logout',
                '/_saml/login',
                '/_saml/metadata',
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
