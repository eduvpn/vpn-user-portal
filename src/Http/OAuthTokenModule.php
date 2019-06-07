<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use fkooman\OAuth\Server\Exception\OAuthException;
use fkooman\OAuth\Server\OAuthServer;

class OAuthTokenModule implements ServiceModuleInterface
{
    /** @var OAuthServer */
    private $oauthServer;

    public function __construct(OAuthServer $oauthServer)
    {
        $this->oauthServer = $oauthServer;
    }

    public function init(Service $service): void
    {
        $service->post(
            '/token',
            /**
             * @return \LC\Portal\Http\Response
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
