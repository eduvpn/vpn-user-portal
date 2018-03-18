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
use SURFnet\VPN\Common\Http\UserInfo;

class BearerAuthenticationHook implements BeforeHookInterface
{
    /** @var \fkooman\OAuth\Server\BearerValidator */
    private $bearerValidator;

    /** @var array */
    private $foreignKeys;

    public function __construct(BearerValidator $bearerValidator, array $foreignKeys)
    {
        $this->bearerValidator = $bearerValidator;
        $this->foreignKeys = $foreignKeys;
    }

    public function executeBefore(Request $request, array $hookData)
    {
        $authorizationHeader = $request->getHeader('HTTP_AUTHORIZATION', false, null);

        try {
            $tokenInfo = $this->bearerValidator->validate($authorizationHeader);

            // require "config" scope
            $tokenInfo->requireAllScope(['config']);
            $publicKey = $tokenInfo->getPublicKey();
            if ($tokenIssuer = array_search($publicKey, $this->foreignKeys, true)) {
                // "bind" the issuer to the user_id
                return new UserInfo(
                    sprintf(
                        '%s_%s',
                        preg_replace('/__*/', '_', preg_replace('/[^A-Za-z0-9.]/', '_', $tokenIssuer)),
                        $tokenInfo->getUserId()
                    )
                );
            }

            return new UserInfo($tokenInfo->getUserId());
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
