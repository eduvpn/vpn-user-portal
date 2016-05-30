<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\VPN\UserPortal;

use fkooman\Http\RedirectResponse;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Http\Session;
use fkooman\OAuth\Client\OAuth2Client;

class VootModule implements ServiceModuleInterface
{
    /** @var \fkooman\OAuth\Client\OAuth2Client */
    private $oauthClient;

    /** @var VpnServerApiClient */
    private $vpnServerApiClient;

    /** @var \fkooman\Http\Session */
    private $session;

    public function __construct(OAuth2Client $oauthClient, VpnServerApiClient $vpnServerApiClient, Session $session)
    {
        $this->oauthClient = $oauthClient;
        $this->vpnServerApiClient = $vpnServerApiClient;
        $this->session = $session;
    }

    public function init(Service $service)
    {
        $userAuth = array(
            'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                'activate' => array('user'),
            ),
        );

        $service->get(
            '/_voot/authorize',
            function (Request $request, UserInfoInterface $u) {
                $authorizationRequestUri = $this->oauthClient->getAuthorizationRequestUri(
                    'groups',
                    $request->getUrl()->getRootUrl().'_voot/callback'
                );

                $this->session->set('_voot_state', $authorizationRequestUri);

                return new RedirectResponse($authorizationRequestUri);
            },
            $userAuth
        );

        $service->get(
            '/_voot/callback',
            function (Request $request, UserInfoInterface $u) {
                // obtain the access token
                $accessToken = $this->oauthClient->getAccessToken(
                    $this->session->get('_voot_state'),
                    $request->getUrl()->getQueryParameter('code'),
                    $request->getUrl()->getQueryParameter('state')
                );
                $this->session->delete('_voot_state');

                // store the access token
                $this->vpnServerApiClient->setVootToken($u->getUserId(), $accessToken->getToken());

                // return to account page
                return new RedirectResponse($request->getUrl()->getRootUrl().'account', 302);
            },
            $userAuth
        );
    }
}
