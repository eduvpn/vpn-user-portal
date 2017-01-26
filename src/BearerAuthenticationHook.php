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

use DateTime;
use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\Exception\BearerException;
use fkooman\OAuth\Server\TokenStorage;
use SURFnet\VPN\Common\Http\BeforeHookInterface;
use SURFnet\VPN\Common\Http\JsonResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;

class BearerAuthenticationHook implements BeforeHookInterface
{
    /** @var \fkooman\OAuth\Server\BearerValidator */
    private $bearerValidator;

    public function __construct(TokenStorage $tokenStorage, DateTime $dateTime)
    {
        $this->bearerValidator = new BearerValidator($tokenStorage, $dateTime);
    }

    public function executeBefore(Request $request, array $hookData)
    {
        $authorizationHeader = $request->getHeader('HTTP_AUTHORIZATION', false, null);

        try {
            $tokenInfo = $this->bearerValidator->validate($authorizationHeader);
            if (false === $tokenInfo) {
                // no Bearer token was provided
                $response = new Response(401);
                $response->addHeader('WWW-Authenticate', 'Bearer realm="OAuth"');

                return $response;
            }

            return $tokenInfo['user_id'];
        } catch (BearerException $e) {
            $response = new JsonResponse(['error' => $e->getMessage(), 'error_description' => $e->getDescription()], 401);
            $response->addHeader('WWW-Authenticate', sprintf('Bearer realm="OAuth",error="%s",error_description="%s"', $e->getMessage(), $e->getDescription()));

            return $response;
        }
    }
}
