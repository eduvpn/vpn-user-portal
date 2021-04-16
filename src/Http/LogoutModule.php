<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

class LogoutModule implements ServiceModuleInterface
{
    private AuthModuleInterface $authModule;
    private SessionInterface $session;

    public function __construct(AuthModuleInterface $authModule, SessionInterface $session)
    {
        $this->authModule = $authModule;
        $this->session = $session;
    }

    public function init(Service $service): void
    {
        $service->post(
            '/_logout',
            function (UserInfo $userInfo, Request $request): Response {
                // XXX for authModules that do support logout, but do not clear the local session we need to do something...
                // maybe we can set a special variable to destroys session on next request in UpdateUserInfoHook?
                return $this->authModule->triggerLogout($request);
            }
        );
    }
}
