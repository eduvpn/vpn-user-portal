<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Http\Exception\HttpException;

class OAuthService extends Service implements ServiceInterface
{
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
