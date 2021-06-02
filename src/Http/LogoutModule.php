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
                // destroy our local session before triggering any (external)
                // mechanism to facilitate logout
                $this->session->destroy();

                return $this->authModule->triggerLogout($request);
            }
        );
    }
}
