<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace SURFnet\VPN\Portal;

use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
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
