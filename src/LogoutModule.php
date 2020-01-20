<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\Http\SessionInterface;

class LogoutModule implements ServiceModuleInterface
{
    /** @var \LC\Common\Http\SessionInterface */
    private $session;

    /** @var string|null */
    private $logoutUrl;

    /** @var string */
    private $returnParameter;

    /**
     * @param string|null $logoutUrl
     * @param string      $returnParameter
     */
    public function __construct(SessionInterface $session, $logoutUrl, $returnParameter)
    {
        $this->session = $session;
        $this->logoutUrl = $logoutUrl;
        $this->returnParameter = $returnParameter;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        // new URL since we introduce SAML / Mellon logout
        $service->post(
            '/_logout',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $httpReferrer = $request->requireHeader('HTTP_REFERER');
                if (null !== $this->logoutUrl) {
                    // we can't destroy the complete session here, we need to
                    // delete the keys one by one as some may be used by e.g.
                    // the SAML authentication backend...
                    $this->session->remove('_update_session_info');
                    $this->session->remove('_saml_auth_time');
                    $this->session->remove('_two_factor_verified');
                    $this->session->remove('_mellon_auth_user');
                    $this->session->remove('_mellon_auth_time');
                    $this->session->remove('_two_factor_enroll_redirect_to');
                    $this->session->remove('_two_factor_verified');
                    $this->session->remove('_form_auth_user');
                    $this->session->remove('_form_auth_permission_list');
                    $this->session->remove('_form_auth_time');

                    // a logout URL is defined, this is used by SAML/Mellon
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
