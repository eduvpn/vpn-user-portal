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
use fkooman\OAuth\Server\OAuthServer;

class OAuthTokenModule implements ServiceModuleInterface
{
    private OAuthServer $oauthServer;

    public function __construct(OAuthServer $oauthServer)
    {
        $this->oauthServer = $oauthServer;
    }

    public function init(Service $service): void
    {
        $service->postBeforeAuth(
            '/token',
            function (Request $request): Response {
                try {
                    $tokenResponse = $this->oauthServer->postToken(
                        $request->getPostParameters(),
                        $request->optionalHeader('PHP_AUTH_USER'),
                        $request->optionalHeader('PHP_AUTH_PW')
                    );

                    return new Response($tokenResponse->getBody(), $tokenResponse->getHeaders(), $tokenResponse->getStatusCode());
                } catch (OAuthException $e) {
                    $jsonResponse = $e->getJsonResponse();

                    return new Response($jsonResponse->getBody(), $jsonResponse->getHeaders(), $jsonResponse->getStatusCode());
                }
            }
        );
    }
}
