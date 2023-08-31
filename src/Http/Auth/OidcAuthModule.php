<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use Vpn\Portal\Cfg\OidcAuthConfig;
use Vpn\Portal\Http\RedirectResponse;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Response;
use Vpn\Portal\Http\UserInfo;

class OidcAuthModule extends AbstractAuthModule
{
    private OidcAuthConfig $config;

    public function __construct(OidcAuthConfig $config)
    {
        $this->config = $config;
    }

    public function userInfo(Request $request): ?UserInfo
    {
        $permissionList = [];
        foreach ($this->config->permissionAttributeList() as $permissionAttribute) {
            // mod_openidc_auth adds the "OIDC_CLAIM_" prefix, test for that as
            // well if the admin did not specifically add that
            foreach (['', 'OIDC_CLAIM_'] as $claimPrefix) {
                if (null !== $permissionAttributeValue = $request->optionalHeader($claimPrefix.$permissionAttribute)) {
                    // OIDCClaimDelimiter for multi-valued claims (default is ",")
                    $permissionList[$permissionAttribute] = explode(',', $permissionAttributeValue);
                }
            }
        }

        return new UserInfo(
            $request->requireHeader($this->config->userIdAttribute()),
            AbstractAuthModule::flattenPermissionList($permissionList)
        );
    }

    public function triggerLogout(Request $request): Response
    {
        return new RedirectResponse(
            // we redirect back to OIDCRedirectURI as defined in the Apache
            // configuration with the "logout" query parameter
            // @see https://github.com/zmartzone/mod_auth_openidc/wiki#9-how-do-i-logout-users
            $request->getRootUri().'redirect_uri?'.http_build_query(['logout' => $request->requireReferrer()])
        );
    }
}
