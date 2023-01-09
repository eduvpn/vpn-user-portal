<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Http\Exception\HttpException;

/**
 * Used from "admin-api.php" to expose an API for admins to create/destroy VPN
 * configuration.
 */
class AdminApiService extends Service implements ServiceInterface
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
