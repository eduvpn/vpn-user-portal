<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Client\OAuthClient;
use fkooman\OAuth\Client\Provider;
use fkooman\SeCookie\SessionInterface;
use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\HttpClient\ServerClient;

class VootModule implements ServiceModuleInterface
{
    /** @var \fkooman\OAuth\Client\OAuthClient */
    private $oauthClient;

    /** @var \fkooman\OAuth\Client\Provider */
    private $provider;

    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    public function __construct(OAuthClient $oauthClient, Provider $provider, ServerClient $serverClient, SessionInterface $session)
    {
        $this->oauthClient = $oauthClient;
        $this->provider = $provider;
        $this->serverClient = $serverClient;
        $this->session = $session;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/_voot/authorize',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];
                $authorizationRequestUri = $this->oauthClient->getAuthorizeUri(
                    $this->provider,
                    $userInfo->id(),
                    'groups',
                    $request->getRootUri().'_voot/callback'
                );

                $this->session->set('_voot_return_to', $request->getQueryParameter('return_to'));

                return new RedirectResponse($authorizationRequestUri);
            }
        );

        $service->get(
            '/_voot/callback',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];
                // obtain the access token
                $this->oauthClient->handleCallback(
                    $this->provider,
                    $userInfo->id(),
                    $request->getQueryParameters()
                );

                $returnTo = $this->session->get('_voot_return_to');
                $this->session->delete('_voot_return_to');

                return new RedirectResponse($returnTo, 302);
            }
        );
    }
}
