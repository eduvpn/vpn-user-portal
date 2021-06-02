<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http\Auth;

use LC\Portal\Http\AuthModuleInterface;
use LC\Portal\Http\RedirectResponse;
use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\UserInfo;

class AbstractAuthModule implements AuthModuleInterface
{
    public function userInfo(Request $request): ?UserInfo
    {
        return null;
    }

    public function startAuth(Request $request): ?Response
    {
        return null;
    }

    public function triggerLogout(Request $request): Response
    {
        // by default we return to the place the users came from, it is up to
        // authentication mechanisms that implement their own logout, e.g.
        // SAML authentication to override this method
        return new RedirectResponse($request->requireHeader('HTTP_REFERER'));
    }
}
