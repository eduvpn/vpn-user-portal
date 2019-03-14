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

class OpenIdcAuthenticationHook implements BeforeHookInterface
{
    /** @var string */
    private $userIdAttribute;

    /**
     * @param string $userIdAttribute
     */
    public function __construct($userIdAttribute)
    {
        $this->userIdAttribute = $userIdAttribute;
    }

    /**
     * @param Request $request
     * @param array   $hookData
     *
     * @return UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
    {
        return new UserInfo(
            $request->requireHeader($this->userIdAttribute),
            [],
            new DateTime()
        );
    }
}
