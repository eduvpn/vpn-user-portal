<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http\Exception;

use Exception;

class HttpException extends Exception
{
    /** @var array */
    private $responseHeaders;

    public function __construct(string $message, int $code, array $responseHeaders = [], Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->responseHeaders = $responseHeaders;
    }

    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }
}
