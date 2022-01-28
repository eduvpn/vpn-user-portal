<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use fkooman\OAuth\Server\Exception\OAuthException;
use Vpn\Portal\Http\Auth\NullAuthModule;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\OAuth\ValidatorInterface;

/**
 * Used from "api.php" to handle the OAuth 2 API calls.
 */
class ApiService extends Service implements ServiceInterface
{
    private ValidatorInterface $bearerValidator;

    public function __construct(ValidatorInterface $bearerValidator)
    {
        parent::__construct(new NullAuthModule());
        $this->bearerValidator = $bearerValidator;
    }

    public function run(Request $request): Response
    {
        try {
            $accessToken = $this->bearerValidator->validate();
            $accessToken->scope()->requireAll(['config']);

            return $this->getRoutePathCallable($request)($accessToken, $request);
        } catch (OAuthException $e) {
            return new Response(
                $e->getJsonResponse()->getBody(),
                $e->getJsonResponse()->getHeaders(),
                $e->getJsonResponse()->getStatusCode()
            );
        } catch (HttpException $e) {
            return new JsonResponse(
                [
                    'error' => $e->getMessage(),
                ],
                $e->responseHeaders(),
                $e->statusCode()
            );
        }
    }
}
