<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

class ApiResponse extends JsonResponse
{
    /**
     * @param mixed $responseData
     */
    public function __construct(string $wrapperKey, $responseData = null, int $statusCode = 200)
    {
        $responseBody = [
            $wrapperKey => [
                'ok' => true,
            ],
        ];

        if (null !== $responseData) {
            $responseBody[$wrapperKey]['data'] = $responseData;
        }

        parent::__construct($responseBody, [], $statusCode);
    }
}
