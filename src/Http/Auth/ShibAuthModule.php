<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use Vpn\Portal\Cfg\ShibAuthConfig;
use Vpn\Portal\Http\RedirectResponse;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Response;
use Vpn\Portal\Http\UserInfo;

class ShibAuthModule extends AbstractAuthModule
{
    private ShibAuthConfig $config;

    public function __construct(ShibAuthConfig $config)
    {
        $this->config = $config;
    }

    public function userInfo(Request $request): ?UserInfo
    {
        $permissionList = [];
        foreach ($this->config->permissionAttributeList() as $permissionAttribute) {
            if (null !== $permissionAttributeValue = $request->optionalHeader($permissionAttribute)) {
                $permissionList = array_merge($permissionList, explode(';', $permissionAttributeValue));
            }
        }

        $settings = [];
        $settings['permissionList'] = $permissionList;
        return new UserInfo(
            $request->requireHeader($this->config->userIdAttribute()),
            $settings
        );
    }

    public function triggerLogout(Request $request): Response
    {
        return new RedirectResponse(
            $request->getScheme().'://'.$request->getAuthority().'/Shibboleth.sso/Logout?'.http_build_query(['return' => $request->requireReferrer()])
        );
    }
}
