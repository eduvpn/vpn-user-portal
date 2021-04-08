<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http\Auth;

use fkooman\OAuth\Server\Exception\OAuthException;
use LC\Portal\Http\AuthModuleInterface;
use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\UserInfo;
use LC\Portal\Http\UserInfoInterface;
use LC\Portal\OAuth\BearerValidator;

/**
 * Validate API credentials used by the VPN apps.
 */
class BearerAuthModule implements AuthModuleInterface
{
    private BearerValidator $bearerValidator;

    public function __construct(BearerValidator $bearerValidator)
    {
        $this->bearerValidator = $bearerValidator;
    }

    public function userInfo(Request $request): ?UserInfoInterface
    {
        if (null === $authorizationHeader = $request->optionalHeader('HTTP_AUTHORIZATION')) {
            return null;
        }

        try {
            $accessTokenInfo = $this->bearerValidator->validate($authorizationHeader);
            $accessTokenInfo->getScope()->requireAll(['config']);

            return $accessTokenInfo;

//            return new UserInfo(
//                // XXX extend this with other stuff, e.g. client_id, ...
//                $accessTokenInfo->getUserId(),
//                []
//            );
        } catch (OAuthException $e) {
            // XXX should we throw our own exception here exposing the OAuth stuff?
            return null;
        }
//
//            $jsonResponse = $e->getJsonResponse();

//            return Response::import(
//                [
//                    'statusCode' => $jsonResponse->getStatusCode(),
//                    'responseHeaders' => $jsonResponse->getHeaders(),
//                    'responseBody' => $jsonResponse->getBody(),
//                ]
//            );
//        }
    }

    public function startAuth(Request $request): ?Response
    {
        return null;
    }

//    /**
//     * @return \LC\Portal\OAuth\VpnAccessTokenInfo|\LC\Portal\Http\Response
//     */
//    public function executeBefore(Request $request, array $hookData)
//    {
//        if (null === $authorizationHeader = $request->optionalHeader('HTTP_AUTHORIZATION')) {
//            $authorizationHeader = '';
//        }

//        try {
//            $accessTokenInfo = $this->bearerValidator->validate($authorizationHeader);
//            // require "config" scope
//            $accessTokenInfo->getScope()->requireAll(['config']);

//            return $accessTokenInfo;
//        } catch (OAuthException $e) {
//            $jsonResponse = $e->getJsonResponse();

//            return Response::import(
//                [
//                    'statusCode' => $jsonResponse->getStatusCode(),
//                    'responseHeaders' => $jsonResponse->getHeaders(),
//                    'responseBody' => $jsonResponse->getBody(),
//                ]
//            );
//        }
//    }
}
