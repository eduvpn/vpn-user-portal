<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\FileIO;
use LC\Portal\Json;

/**
 * XXX not used at the moment!
 */
class StaticPermissions
{
    /** @var string */
    private $permissionFile;

    public function __construct(string $permissionFile)
    {
        $this->permissionFile = $permissionFile;
    }

    /**
     * @return array<string>
     */
    public function get(string $userId): array
    {
        $groupData = Json::decode(FileIO::readFile($this->permissionFile));
        $permissionList = [];
        foreach ($groupData as $permissionId => $memberList) {
            if (!\in_array($userId, $memberList, true)) {
                continue;
            }
            $permissionList[] = $permissionId;
        }

        return $permissionList;
    }
}
