<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use fkooman\OAuth\Server\Exception\OAuthException;
use LC\Portal\OAuth\BearerValidatorInterface;

class BearerAuthenticationHook implements BeforeHookInterface
{
    /** @var \LC\Portal\OAuth\BearerValidatorInterface */
    private $bearerValidator;

    public function __construct(BearerValidatorInterface $bearerValidator)
    {
        $this->bearerValidator = $bearerValidator;
    }

    /**
     * @return \LC\Portal\OAuth\VpnAccessTokenInfo|\LC\Portal\Http\Response
     */
    public function executeBefore(Request $request, array $hookData)
    {
        if (null === $authorizationHeader = $request->optionalHeader('HTTP_AUTHORIZATION')) {
            $authorizationHeader = '';
        }

        try {
            $accessTokenInfo = $this->bearerValidator->validate($authorizationHeader);
            // require "config" scope
            $accessTokenInfo->getScope()->requireAll(['config']);

            return $accessTokenInfo;
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
