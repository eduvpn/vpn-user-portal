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
use fkooman\OAuth\Server\Exception\TokenException;
use fkooman\OAuth\Server\TokenStorage;
use fkooman\OAuth\Server\Validator;
use SURFnet\VPN\Common\Http\BeforeHookInterface;
use SURFnet\VPN\Common\Http\JsonResponse;
use SURFnet\VPN\Common\Http\Request;

class BearerAuthenticationHook implements BeforeHookInterface
{
    /** @var \fkooman\OAuth\Server\Validator */
    private $validator;

    public function __construct(TokenStorage $tokenStorage, DateTime $dateTime)
    {
        $this->validator = new Validator($tokenStorage, $dateTime);
    }

    public function executeBefore(Request $request, array $hookData)
    {
        $authorizationHeader = $request->getHeader('HTTP_AUTHORIZATION', false, null);

        try {
            $tokenInfo = $this->validator->validate($authorizationHeader);

            return $tokenInfo['user_id'];
        } catch (TokenException $e) {
            $tokenResponse = $e->getResponse();

            $response = new JsonResponse($tokenResponse->getBody(true), $tokenResponse->getStatusCode());
            foreach ($tokenResponse->getHeaders() as $k => $v) {
                $response->addHeader($k, $v);
            }

            return $response;
        }
    }
}
