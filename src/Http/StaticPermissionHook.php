<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\FileIO;
use Vpn\Portal\Json;

/**
 * Add "static" permissions to specific users based on a JSON file that
 * maps permissions to lists of users.
 */
class StaticPermissionHook extends AbstractHook implements HookInterface
{
    private string $staticPermissionFile;

    public function __construct(string $staticPermissionFile)
    {
        $this->staticPermissionFile = $staticPermissionFile;
    }

    public function afterAuth(Request $request, UserInfo &$userInfo): ?Response
    {
        $permissionData = Json::decode(FileIO::read($this->staticPermissionFile));
        $permissionList = $userInfo->permissionList();
        foreach ($permissionData as $permissionId => $userIdList) {
            if (!is_string($permissionId)) {
                continue;
            }
            if (!is_array($userIdList)) {
                continue;
            }
            if (in_array($userInfo->userId(), $userIdList, true)) {
                $permissionList[] = $permissionId;
            }
        }
        $userInfo->setPermissionList($permissionList);

        return null;
    }
}
