<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Exception;

use Exception;

class HttpException extends Exception
{
    private int $statusCode;

    /** @var array<string,string> */
    private array $responseHeaders;

    /**
     * @param array<string,string> $responseHeaders
     */
    public function __construct(string $message, int $statusCode, array $responseHeaders = [])
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->responseHeaders = $responseHeaders;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string,string>
     */
    public function responseHeaders(): array
    {
        return $this->responseHeaders;
    }
}
