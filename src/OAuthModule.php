<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use fkooman\OAuth\Server\Exception\OAuthException;
use fkooman\OAuth\Server\Http\Response as OAuthResponse;
use fkooman\OAuth\Server\OAuthServer;
use LetsConnect\Common\Http\Exception\HttpException;
use LetsConnect\Common\Http\HtmlResponse;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Response;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\Http\ServiceModuleInterface;
use LetsConnect\Common\TplInterface;

class OAuthModule implements ServiceModuleInterface
{
    /** @var \LetsConnect\Common\TplInterface */
    private $tpl;

    /** @var OAuthServer */
    private $oauthServer;

    public function __construct(TplInterface $tpl, OAuthServer $oauthServer)
    {
        $this->tpl = $tpl;
        $this->oauthServer = $oauthServer;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/_oauth/authorize',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];
                try {
                    if ($authorizeResponse = $this->oauthServer->getAuthorizeResponse($request->getQueryParameters(), $userInfo->id())) {
                        // optimization where we do not ask for approval
                        return $this->prepareReturnResponse($authorizeResponse);
                    }

                    // ask for approving this client/scope
                    return new HtmlResponse(
                        $this->tpl->render(
                            'authorizeOAuthClient',
                            $this->oauthServer->getAuthorize($request->getQueryParameters())
                        )
                    );
                } catch (OAuthException $e) {
                    throw new HttpException(sprintf('ERROR: %s (%s)', $e->getMessage(), $e->getDescription()), $e->getCode());
                }
            }
        );

        $service->post(
            '/_oauth/authorize',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];

                try {
                    $authorizeResponse = $this->oauthServer->postAuthorize(
                        $request->getQueryParameters(),
                        $request->getPostParameters(),
                        $userInfo->id()
                    );

                    return $this->prepareReturnResponse($authorizeResponse);
                } catch (OAuthException $e) {
                    throw new HttpException(sprintf('ERROR: %s (%s)', $e->getMessage(), $e->getDescription()), $e->getCode());
                }
            }
        );
    }

    /**
     * @param \fkooman\OAuth\Server\Http\Response $authorizeResponse
     *
     * @return \LetsConnect\Common\Http\Response
     */
    private function prepareReturnResponse(OAuthResponse $authorizeResponse)
    {
        return Response::import(
            [
                'statusCode' => $authorizeResponse->getStatusCode(),
                'responseHeaders' => $authorizeResponse->getHeaders(),
                'responseBody' => $authorizeResponse->getBody(),
            ]
        );
    }
}
