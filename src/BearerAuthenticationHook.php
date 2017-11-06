<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\Exception\BearerException;
use SURFnet\VPN\Common\Http\BeforeHookInterface;
use SURFnet\VPN\Common\Http\JsonResponse;
use SURFnet\VPN\Common\Http\Request;

class BearerAuthenticationHook implements BeforeHookInterface
{
    /** @var \fkooman\OAuth\Server\BearerValidator */
    private $bearerValidator;

    public function __construct(BearerValidator $bearerValidator)
    {
        $this->bearerValidator = $bearerValidator;
    }

    public function executeBefore(Request $request, array $hookData)
    {
        $authorizationHeader = $request->getHeader('HTTP_AUTHORIZATION', false, null);

        try {
            $tokenInfo = $this->bearerValidator->validate($authorizationHeader);

            $tokenIssuer = $tokenInfo->getIssuer();
            if (null !== $tokenIssuer) {
                // "bind" the issuer to the user_id
                return sprintf(
                    '%s_%s',
                    preg_replace('/__*/', '_', preg_replace('/[^A-Za-z0-9.]/', '_', $tokenIssuer)),
                    $tokenInfo->getUserId()
                );
            }

            return $tokenInfo->getUserId();
        } catch (BearerException $e) {
            $response = new JsonResponse(['error' => $e->getMessage(), 'error_description' => $e->getDescription()], 401);
            $response->addHeader('WWW-Authenticate', sprintf('Bearer realm="OAuth",error="%s",error_description="%s"', $e->getMessage(), $e->getDescription()));

            return $response;
        }
    }
}
