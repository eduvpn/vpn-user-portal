<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Json;

class ApiErrorResponse extends Response
{
    public function __construct(string $wrapperKey, string $errorMessage, int $responseCode = 200)
    {
        parent::__construct($responseCode, 'application/json');

        $responseBody = [
            $wrapperKey => [
                'ok' => false,
                'error' => $errorMessage,
            ],
        ];

        $this->setBody(Json::encode($responseBody));
    }
}
