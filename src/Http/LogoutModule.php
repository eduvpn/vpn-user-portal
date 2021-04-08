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
    private SessionInterface $session;

    private ?string $logoutUrl;

    private string $returnParameter;

    public function __construct(SessionInterface $session, ?string $logoutUrl, string $returnParameter)
    {
        $this->session = $session;
        $this->logoutUrl = $logoutUrl;
        $this->returnParameter = $returnParameter;
    }

    public function init(Service $service): void
    {
        $service->post(
            '/_logout',
            function (UserInfo $userInfo, Request $request): Response {
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
