<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use DateTime;
use LetsConnect\Common\Http\BeforeHookInterface;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\UserInfo;

class ShibAuthenticationHook implements BeforeHookInterface
{
    /** @var string */
    private $userIdAttribute;

    /** @var string|null */
    private $permissionAttribute;

    /**
     * @param string      $userIdAttribute
     * @param string|null $permissionAttribute
     */
    public function __construct($userIdAttribute, $permissionAttribute)
    {
        $this->userIdAttribute = $userIdAttribute;
        $this->permissionAttribute = $permissionAttribute;
    }

    /**
     * @param Request $request
     * @param array   $hookData
     *
     * @return UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
    {
        $userPermissions = [];
        if (null !== $this->permissionAttribute) {
            $userPermissions = explode(';', $request->requireHeader($this->permissionAttribute));
        }

        return new UserInfo(
            $request->requireHeader($this->userIdAttribute),
            $userPermissions,
            new DateTime($request->requireHeader('Shib-Authentication-Instant'))
        );
    }
}
