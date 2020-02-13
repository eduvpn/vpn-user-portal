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
        $service->post(
            '/_logout',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $this->session->destroy();

                // figure out where to return after logout
                $httpReferrer = $request->requireHeader('HTTP_REFERER');

                if (null === $logoutUrl = $this->logoutUrl) {
                    // no external authentication source we need to go to to
                    // complete the logout
                    return new RedirectResponse($httpReferrer);
                }

                // we have an external authentication module that wants to
                // be triggered on logout before returning to the place we came
                // from
                return new RedirectResponse(
                    sprintf(
                        '%s%s%s',
                        $logoutUrl,
                        false === strpos($logoutUrl, '?') ? '?' : '&',
                        http_build_query(
                            [
                                $this->returnParameter => $httpReferrer,
                            ]
                        )
                    )
                );
            }
        );
    }
}
