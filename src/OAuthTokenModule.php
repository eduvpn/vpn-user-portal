<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\OAuth\Server\Exception\OAuthException;
use fkooman\OAuth\Server\OAuthServer;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;

class OAuthTokenModule implements ServiceModuleInterface
{
    /** @var OAuthServer */
    private $oauthServer;

    public function __construct(OAuthServer $oauthServer)
    {
        $this->oauthServer = $oauthServer;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->post(
            '/token',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                try {
                    $tokenResponse = $this->oauthServer->postToken(
                        $request->getPostParameters(),
                        $request->optionalHeader('PHP_AUTH_USER'),
                        $request->optionalHeader('PHP_AUTH_PW')
                    );

                    return Response::import(
                        [
                            'statusCode' => $tokenResponse->getStatusCode(),
                            'responseHeaders' => $tokenResponse->getHeaders(),
                            'responseBody' => $tokenResponse->getBody(),
                        ]
                    );
                } catch (OAuthException $e) {
                    $jsonResponse = $e->getJsonResponse();

                    return Response::import(
                        [
                            'statusCode' => $jsonResponse->getStatusCode(),
                            'responseHeaders' => $jsonResponse->getHeaders(),
                            'responseBody' => $jsonResponse->getBody(),
                        ]
                    );
                }
            }
        );
    }
}
