<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Request;
use LC\Common\Http\UserInfo;

class OpenidcAuthenticationHook implements BeforeHookInterface
{
    /** @var string */
    private $subjectClaim;

    /** @var string|null */
    private $permissionClaim;

    /**
     * @param string      $subjectClaim
     * @param string|null $permissionClaim
     */
    public function __construct($subjectClaim, $permissionClaim)
    {
        $this->subjectClaim = $subjectClaim;
        $this->permissionClaim = $permissionClaim;
    }

    /**
     * @return UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
    {
        $userPermissions = [];
        if (null !== $this->permissionClaim) {
            $permissionHeaderValue = $request->optionalHeader($this->permissionClaim);
            if (null !== $permissionClaim) {
                $userPermissions = explode(',', $permissionHeaderValue);
            }
        }

        return new UserInfo(
            $request->requireHeader($this->subjectClaim),
            $userPermissions
        );
    }
}
