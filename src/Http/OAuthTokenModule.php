<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use fkooman\OAuth\Server\Exception\OAuthException;
use fkooman\OAuth\Server\OAuthServer;

class OAuthTokenModule implements ServiceModuleInterface
{
    private OAuthServer $oauthServer;

    public function __construct(OAuthServer $oauthServer)
    {
        $this->oauthServer = $oauthServer;
    }

    public function init(ServiceInterface $service): void
    {
        $service->postBeforeAuth(
            '/oauth/token',
            function (Request $request): Response {
                try {
                    $tokenResponse = $this->oauthServer->postToken();

                    return new Response($tokenResponse->getBody(), $tokenResponse->getHeaders(), $tokenResponse->getStatusCode());
                } catch (OAuthException $e) {
                    $jsonResponse = $e->getJsonResponse();

                    return new Response($jsonResponse->getBody(), $jsonResponse->getHeaders(), $jsonResponse->getStatusCode());
                }
            }
        );
    }
}
