<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

/**
 * Registers an endpoint that the portal "POSTs" to in order to trigger
 * the logout action. It will _also_ allow for authentication mechanisms to
 * "do something extra" in case logout is triggered, for example stop the SAML
 * session when using a SAML authentication backend.
 *
 * NOTE: not all authentication mechanisms support logout, e.g. BasicAuth or
 * ClientCertAuth, they will require the user to close the browser or restart
 * the device.
 */
class LogoutModule implements ServiceModuleInterface
{
    private AuthModuleInterface $authModule;
    private SessionInterface $session;

    public function __construct(AuthModuleInterface $authModule, SessionInterface $session)
    {
        $this->authModule = $authModule;
        $this->session = $session;
    }

    public function init(ServiceInterface $service): void
    {
        $service->post(
            '/_logout',
            function (Request $request, UserInfo $userInfo): Response {
                // destroy our local session before triggering any (external)
                // mechanism to facilitate logout
                $this->session->destroy();

                return $this->authModule->triggerLogout($request);
            }
        );
    }
}
