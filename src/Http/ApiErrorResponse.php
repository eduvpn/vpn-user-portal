<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

class ApiErrorResponse extends JsonResponse
{
    public function __construct(string $wrapperKey, string $errorMessage, int $statusCode = 200)
    {
        parent::__construct(
            [
                $wrapperKey => [
                    'ok' => false,
                    'error' => $errorMessage,
                ],
            ],
            [],
            $statusCode
        );
    }
}
