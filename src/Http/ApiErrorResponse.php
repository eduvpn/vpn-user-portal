<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Json;

class ApiErrorResponse extends Response
{
    /**
     * @param string $wrapperKey
     * @param string $errorMessage
     * @param int    $responseCode
     */
    public function __construct($wrapperKey, $errorMessage, $responseCode = 200)
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
