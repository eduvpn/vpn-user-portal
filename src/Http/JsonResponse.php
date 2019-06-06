<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Json;

class JsonResponse extends Response
{
    /**
     * @param array $responseData
     * @param int   $responseCode
     */
    public function __construct(array $responseData, $responseCode = 200)
    {
        parent::__construct($responseCode, 'application/json');
        $this->setBody(Json::encode($responseData));
    }
}
