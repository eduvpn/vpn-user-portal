<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Http\Auth\NullAuthModule;
use Vpn\Portal\Http\Exception\HttpException;

/**
 * Used from "oauth.php" to handle the OAuth 2 /token calls.
 */
class OAuthTokenService extends Service implements ServiceInterface
{
    public function __construct()
    {
        // the OAuthTokenModule implements its own authentication, it does not
        // use the built-in authentication mechanism
        parent::__construct(new NullAuthModule());
    }

    public function run(Request $request): Response
    {
        try {
            return parent::run($request);
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
