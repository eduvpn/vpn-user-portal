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

class JsonResponse extends Response
{
    public function __construct(array $responseData, int $responseCode = 200)
    {
        parent::__construct($responseCode, 'application/json');
        $this->setBody(Json::encode($responseData));
    }
}
