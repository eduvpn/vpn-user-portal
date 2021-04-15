<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use fkooman\OAuth\Server\Exception\OAuthException;
use fkooman\OAuth\Server\Http\Response as OAuthResponse;
use fkooman\OAuth\Server\OAuthServer;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\TplInterface;

class OAuthModule implements ServiceModuleInterface
{
    private TplInterface $tpl;

    private OAuthServer $oauthServer;

    public function __construct(TplInterface $tpl, OAuthServer $oauthServer)
    {
        $this->tpl = $tpl;
        $this->oauthServer = $oauthServer;
    }

    public function init(Service $service): void
    {
        $service->get(
            '/_oauth/authorize',
            function (UserInfo $userInfo, Request $request): Response {
                try {
                    if ($authorizeResponse = $this->oauthServer->getAuthorizeResponse($request->getQueryParameters(), $userInfo->getUserId())) {
                        // optimization where we do not ask for approval
                        return $this->prepareReturnResponse($authorizeResponse);
                    }

                    // ask for approving this client/scope
                    return new HtmlResponse(
                        $this->tpl->render(
                            'authorizeOAuthClient',
                            array_merge(
                                [
                                    '_show_logout_button' => false,
                                ],
                                $this->oauthServer->getAuthorize($request->getQueryParameters())
                            )
                        )
                    );
                } catch (OAuthException $e) {
                    throw new HttpException(sprintf('ERROR: %s (%s)', $e->getMessage(), $e->getDescription()), $e->getStatusCode());
                }
            }
        );

        $service->post(
            '/_oauth/authorize',
            function (UserInfo $userInfo, Request $request): Response {
                try {
                    $authorizeResponse = $this->oauthServer->postAuthorize(
                        $request->getQueryParameters(),
                        $request->getPostParameters(),
                        $userInfo->getUserId()
                    );

                    return $this->prepareReturnResponse($authorizeResponse);
                } catch (OAuthException $e) {
                    throw new HttpException(sprintf('ERROR: %s (%s)', $e->getMessage(), $e->getDescription()), $e->getStatusCode());
                }
            }
        );
    }

    private function prepareReturnResponse(OAuthResponse $authorizeResponse): Response
    {
        return new Response($authorizeResponse->getBody(), $authorizeResponse->getHeaders(), $authorizeResponse->getStatusCode());
    }
}
