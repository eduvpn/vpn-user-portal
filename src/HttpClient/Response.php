<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\HttpClient;

use LC\Portal\Json;

class Response
{
    /** @var int */
    private $statusCode;

    /** @var string */
    private $responseBody;

    /** @var array<string,string> */
    private $responseHeaders;

    /**
     * @param array<string,string> $responseHeaders
     */
    public function __construct(int $statusCode, string $responseBody, array $responseHeaders = [])
    {
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
        $this->responseHeaders = $responseHeaders;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->responseBody;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->responseHeaders;
    }

    public function getHeader(string $key): ?string
    {
        foreach ($this->responseHeaders as $k => $v) {
            if (strtoupper($key) === strtoupper($k)) {
                return $v;
            }
        }

        return null;
    }

    public function json(): array
    {
        return Json::decode($this->responseBody);
    }

    public function isOkay(): bool
    {
        return 200 <= $this->statusCode && 300 > $this->statusCode;
    }
}
