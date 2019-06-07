<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use fkooman\SeCookie\SessionInterface;

class LogoutModule implements ServiceModuleInterface
{
    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var string|null */
    private $logoutUrl;

    /** @var string */
    private $returnParameter;

    public function __construct(SessionInterface $session, ?string $logoutUrl, string $returnParameter)
    {
        $this->session = $session;
        $this->logoutUrl = $logoutUrl;
        $this->returnParameter = $returnParameter;
    }

    public function init(Service $service): void
    {
        // new URL since we introduce SAML logout
        $service->post(
            '/_logout',
            function (Request $request, array $hookData): Response {
                $httpReferrer = $request->requireHeader('HTTP_REFERER');
                if (null !== $this->logoutUrl) {
                    // we can't destroy the complete session here, we need to
                    // delete the keys one by one as some may be used by e.g.
                    // the SAML authentication backend...
                    $this->session->delete('_update_session_info');
                    $this->session->delete('_saml_auth_time');
                    $this->session->delete('_two_factor_verified');
                    $this->session->delete('_two_factor_enroll_redirect_to');
                    $this->session->delete('_two_factor_verified');
                    $this->session->delete('_form_auth_user');
                    $this->session->delete('_form_auth_permission_list');
                    $this->session->delete('_form_auth_time');

                    // a logout URL is defined, this is used by SAML
                    return new RedirectResponse(
                        sprintf(
                            '%s?%s',
                            $this->logoutUrl,
                            http_build_query(
                                [
                                    $this->returnParameter => $httpReferrer,
                                ]
                            )
                        )
                    );
                }

                $this->session->destroy();

                return new RedirectResponse($httpReferrer);
            }
        );
    }
}
