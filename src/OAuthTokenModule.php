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
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;

class OAuthTokenModule implements ServiceModuleInterface
{
    /** @var OAuthServer */
    private $oauthServer;

    public function __construct(OAuthServer $oauthServer)
    {
        $this->oauthServer = $oauthServer;
    }

    public function init(Service $service)
    {
        $service->post(
            '/token',
            function (Request $request, array $hookData) {
                try {
                    $tokenResponse = $this->oauthServer->postToken(
                        $request->getPostParameters(),
                        $request->getHeader('PHP_AUTH_USER', false),
                        $request->getHeader('PHP_AUTH_PW', false)
                    );

                    return Response::import(
                        [
                            'statusCode' => $tokenResponse->getStatusCode(),
                            'responseHeaders' => $tokenResponse->getHeaders(),
                            'responseBody' => $tokenResponse->getBody(),
                        ]
                    );
                } catch (OAuthException $e) {
                    return Response::import(
                        [
                            'statusCode' => $e->getResponse()->getStatusCode(),
                            'responseHeaders' => $e->getResponse()->getHeaders(),
                            'responseBody' => $e->getResponse()->getBody(),
                        ]
                    );
                }
            }
        );
    }
}
