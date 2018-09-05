<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\Exception\OAuthException;
use SURFnet\VPN\Common\Http\BeforeHookInterface;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;

class BearerAuthenticationHook implements BeforeHookInterface
{
    /** @var \fkooman\OAuth\Server\BearerValidator */
    private $bearerValidator;

    public function __construct(BearerValidator $bearerValidator)
    {
        $this->bearerValidator = $bearerValidator;
    }

    /**
     * @return \fkooman\OAuth\Server\TokenInfo|\SURFnet\VPN\Common\Http\Response
     */
    public function executeBefore(Request $request, array $hookData)
    {
        if (null === $authorizationHeader = $request->optionalHeader('HTTP_AUTHORIZATION')) {
            $authorizationHeader = '';
        }

        try {
            $tokenInfo = $this->bearerValidator->validate($authorizationHeader);
            // require "config" scope
            $tokenInfo->requireAllScope(['config']);

            return $tokenInfo;
        } catch (OAuthException $e) {
            $jsonResponse = $e->getJsonResponse();

            return Response::import(
                [
                    'statusCode' => $jsonResponse->getStatusCode(),
                    'responseHeaders' => $jsonResponse->getHeaders(),
                    'responseBody' => $jsonResponse->getBody(),
                ]
            );
        }
    }
}
