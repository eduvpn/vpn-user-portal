<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

class StaticPermissionSource implements PermissionSourceInterface
{
    private string $staticPermissionFile;

    public function __construct(string $staticPermissionFile)
    {
        $this->staticPermissionFile = $staticPermissionFile;
    }

    /**
     * @return array<string>
     */
    public function get(string $userId): array
    {
        if (!FileIO::exists($this->staticPermissionFile)) {
            return [];
        }

        $permissionData = Json::decode(FileIO::read($this->staticPermissionFile));
        $permissionList = [];
        foreach ($permissionData as $permissionId => $userIdList) {
            if (!is_string($permissionId)) {
                continue;
            }
            if (!is_array($userIdList)) {
                continue;
            }
            if (in_array($userId, $userIdList, true)) {
                $permissionList[] = $permissionId;
            }
        }

        return array_values(array_unique($permissionList));
    }
}
