<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\HttpClient;

class HttpClientResponse
{
    private int $statusCode;
    private string $headerList;
    private string $responseBody;

    public function __construct(int $statusCode, string $headerList, string $responseBody)
    {
        $this->statusCode = $statusCode;
        $this->headerList = $headerList;
        $this->responseBody = $responseBody;
    }

    public function __toString(): string
    {
        return $this->statusCode().' '.$this->body().' ['.$this->headerList().']';
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * We loop over all available headers and return the value of the first
     * matching header key. If multiple headers with the same name are present
     * the next ones are ignored!
     */
    public function header(string $headerKey): ?string
    {
        foreach (explode("\n", $this->headerList) as $headerLine) {
            if (false === strpos($headerLine, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $headerLine, 2);
            if (strtolower(trim($headerKey)) === strtolower(trim($k))) {
                return trim($v);
            }
        }

        return null;
    }

    public function headerList(): string
    {
        return $this->headerList;
    }

    public function body(): string
    {
        return $this->responseBody;
    }

    public function isOkay(): bool
    {
        return 200 <= $this->statusCode && 300 > $this->statusCode;
    }
}
