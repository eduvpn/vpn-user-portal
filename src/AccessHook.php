<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\Request;
use LC\Common\Http\Service;

/**
 * This hook is used to check if a user is allowed to access the portal/API.
 */
class AccessHook implements BeforeHookInterface
{
    /** @var array<string> */
    private $accessPermissionList;

    /**
     * @param array<string> $accessPermissionList
     */
    public function __construct(array $accessPermissionList)
    {
        $this->accessPermissionList = $accessPermissionList;
    }

    public function executeBefore(Request $request, array $hookData)
    {
        $whiteList = [
            'POST' => [
                '/_form/auth/verify',
                '/_logout',
            ],
        ];
        if (Service::isWhitelisted($request, $whiteList)) {
            return null;
        }

        if (!\array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }
        /** @var \LC\Common\Http\UserInfo */
        $userInfo = $hookData['auth'];
        if (!$this->hasPermissions($userInfo->getPermissionList())) {
            throw new HttpException('account is not allowed to access this service', 403);
        }

        return null;
    }

    /**
     * @param array<string> $userPermissionList
     *
     * @return bool
     */
    private function hasPermissions(array $userPermissionList)
    {
        foreach ($userPermissionList as $userPermission) {
            if (\in_array($userPermission, $this->accessPermissionList, true)) {
                return true;
            }
        }

        return false;
    }
}
