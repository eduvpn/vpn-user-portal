<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Server\Exception\OAuthException;
use fkooman\OAuth\Server\OAuthServer;
use SURFnet\VPN\Common\Http\Exception\HttpException;
use SURFnet\VPN\Common\Http\HtmlResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\TplInterface;

class OAuthModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\TplInterface */
    private $tpl;

    /** @var OAuthServer */
    private $oauthServer;

    public function __construct(TplInterface $tpl, OAuthServer $oauthServer)
    {
        $this->tpl = $tpl;
        $this->oauthServer = $oauthServer;
    }

    public function init(Service $service)
    {
        $service->get(
            '/_oauth/authorize',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];
                try {
                    if ($authorizeResponse = $this->oauthServer->getAuthorizeResponse($request->getQueryParameters(), $userId)) {
                        // optmization where we do not ask for approval
                        return Response::import(
                            [
                                'statusCode' => $authorizeResponse->getStatusCode(),
                                'responseHeaders' => $authorizeResponse->getHeaders(),
                                'responseBody' => $authorizeResponse->getBody(),
                            ]
                        );
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
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                try {
                    $authorizeResponse = $this->oauthServer->postAuthorize(
                        $request->getQueryParameters(),
                        $request->getPostParameters(),
                        $userId
                    );

                    return Response::import(
                        [
                            'statusCode' => $authorizeResponse->getStatusCode(),
                            'responseHeaders' => $authorizeResponse->getHeaders(),
                            'responseBody' => $authorizeResponse->getBody(),
                        ]
                    );
                } catch (OAuthException $e) {
                    throw new HttpException(sprintf('ERROR: %s (%s)', $e->getMessage(), $e->getDescription()), $e->getCode());
                }
            }
        );
    }
}
