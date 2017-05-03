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

use fkooman\OAuth\Server\Exception\OAuthException;
use fkooman\OAuth\Server\OAuthServer;
use SURFnet\VPN\Common\Http\JsonResponse;
use SURFnet\VPN\Common\Http\Request;
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
                    $responseData = $this->oauthServer->postToken(
                        $request->getPostParameters(),
                        $request->getHeader('PHP_AUTH_USER', false),
                        $request->getHeader('PHP_AUTH_PW', false)
                    );
                    $response = new JsonResponse($responseData);
                    $response->addHeader('Cache-Control', 'no-store');
                    $response->addHeader('Pragma', 'no-cache');

                    return $response;
                } catch (OAuthException $e) {
                    $responseData = ['error' => $e->getMessage(), 'error_description' => $e->getDescription()];
                    $response = new JsonResponse($responseData, $e->getCode());
                    $response->addHeader('Cache-Control', 'no-store');
                    $response->addHeader('Pragma', 'no-cache');

                    return $response;
                }
            }
        );
    }
}
