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
namespace SURFnet\VPN\Portal\OAuth;

use SURFnet\VPN\Common\Http\Exception\HttpException;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\HtmlResponse;
use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\TplInterface;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Config;

class OAuthModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\TplInterface */
    private $tpl;

    /** @var RandomInterface */
    private $random;

    /** @var TokenStorage */
    private $tokenStorage;

    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    public function __construct(TplInterface $tpl, RandomInterface $random, TokenStorage $tokenStorage, Config $config)
    {
        $this->tpl = $tpl;
        $this->random = $random;
        $this->tokenStorage = $tokenStorage;
        $this->config = $config;
    }

    public function init(Service $service)
    {
        $service->get(
            '/_oauth/authorize',
            function (Request $request) {
                $this->validateRequest($request);
                $this->validateClient($request);

                // ask for approving this client/scope
                return new HtmlResponse(
                    $this->tpl->render(
                        'authorizeOAuthClient',
                        [
                            'client_id' => $request->getQueryParameter('client_id'),
                            'scope' => $request->getQueryParameter('scope'),
                            'redirect_uri' => $request->getQueryParameter('redirect_uri'),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/_oauth/authorize',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $this->validateRequest($request);
                $this->validateClient($request);

                if ('no' === $request->getPostParameter('approve')) {
                    $redirectQuery = http_build_query(
                        [
                        'error' => 'access_denied',
                        'error_description' => 'user refused authorization',
                        'state' => $request->getQueryParameter('state'),
                        ]
                    );

                    $redirectUri = sprintf('%s#%s', $request->getQueryParameter('redirect_uri'), $redirectQuery);

                    return new RedirectResponse($redirectUri, 302);
                }

                $accessTokenKey = $this->random->get(8);
                $accessToken = $this->random->get(16);

                // store access_token
                $this->tokenStorage->store(
                    $userId,
                    $accessTokenKey,
                    $accessToken,
                    $request->getQueryParameter('client_id'),
                    $request->getQueryParameter('scope')
                );

                // add state, access_token to redirect_uri
                $redirectQuery = http_build_query(
                    [
                        'access_token' => sprintf('%s.%s', $accessTokenKey, $accessToken),
                        'state' => $request->getQueryParameter('state'),
                    ]
                );

                $redirectUri = sprintf('%s#%s', $request->getQueryParameter('redirect_uri'), $redirectQuery);

                return new RedirectResponse($redirectUri, 302);
            }
        );
    }

    private function validateRequest(Request $request)
    {
        // we enforce that all parameter are, nothing is "OPTIONAL"
        $clientId = $request->getQueryParameter('client_id');
        if (1 !== preg_match('/^(?:[\x20-\x7E])+$/', $clientId)) {
            throw new HttpException('invalid client_id', 400);
        }
        $redirectUri = $request->getQueryParameter('redirect_uri');
        if (false === filter_var($redirectUri, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED | FILTER_FLAG_PATH_REQUIRED)) {
            throw new HttpException('invalid redirect_uri', 400);
        }
        $responseType = $request->getQueryParameter('response_type');
        if ('token' !== $responseType) {
            throw new HttpException('invalid response_type', 400);
        }
        $scope = $request->getQueryParameter('scope');
        $supportedScopes = ['create_config'];
        if (!in_array($scope, $supportedScopes)) {
            throw new HttpException('invalid scope', 400);
        }
        $state = $request->getQueryParameter('state');
        if (1 !== preg_match('/^(?:[\x20-\x7E])+$/', $state)) {
            throw new HttpException('invalid state', 400);
        }
    }

    private function validateClient(Request $request)
    {
        $clientId = $request->getQueryParameter('client_id');
        $redirectUri = $request->getQueryParameter('redirect_uri');

        // check if we have a client with this clientId and redirectUri
        if (false === $this->config->e('apiConsumers', $clientId)) {
            throw new HttpException(sprintf('client "%s" not registered', $clientId), 400);
        }
        $clientRedirectUri = $this->config->v('apiConsumers', $clientId, 'redirect_uri');
        if ($redirectUri !== $clientRedirectUri) {
            throw new HttpException(sprintf('redirect_uri does not match expected value "%s"', $clientRedirectUri), 400);
        }
    }
}
