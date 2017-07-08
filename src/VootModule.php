<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Client\OAuthClient;
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

    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    public function __construct(OAuthClient $oauthClient, ServerClient $serverClient, SessionInterface $session)
    {
        $this->oauthClient = $oauthClient;
        $this->serverClient = $serverClient;
        $this->session = $session;
    }

    public function init(Service $service)
    {
        $service->get(
            '/_voot/authorize',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $this->oauthClient->setUserId($userId);
                $authorizationRequestUri = $this->oauthClient->getAuthorizeUri(
                    'groups',
                    $request->getRootUri().'_voot/callback'
                );

//                $this->session->set('_voot_state', $authorizationRequestUri);
                $this->session->set('_voot_return_to', $request->getQueryParameter('return_to'));

                return new RedirectResponse($authorizationRequestUri);
            }
        );

        $service->get(
            '/_voot/callback',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $this->oauthClient->setUserId($userId);
                // obtain the access token
                $this->oauthClient->handleCallback(
//                    $this->session->get('_voot_state'),
                    $request->getQueryParameter('code'),
                    $request->getQueryParameter('state')
                );
//                $this->session->delete('_voot_state');

                $returnTo = $this->session->get('_voot_return_to');
                $this->session->delete('_voot_return_to');

                return new RedirectResponse($returnTo, 302);
            }
        );
    }
}
