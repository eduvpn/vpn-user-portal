<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\Http\Auth\AbstractAuthModule;

class StaticPermissionSource implements PermissionSourceInterface
{
    private string $staticPermissionFile;
    private string $attributeName;

    public function __construct(string $staticPermissionFile, string $attributeName = 'isMemberOf')
    {
        $this->staticPermissionFile = $staticPermissionFile;
        $this->attributeName = $attributeName;
    }

    /**
     * Get current attributes for users directly from the source.
     *
     * If no attributes are available, or the user no longer exists, an empty
     * array is returned.
     *
     * @return array<string>
     */
    public function attributesForUser(string $userId): array
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

        return AbstractAuthModule::flattenPermissionList(
            [
                $this->attributeName => array_values(array_unique($permissionList)),
            ],
            null,
            'S'
        );
    }
}
