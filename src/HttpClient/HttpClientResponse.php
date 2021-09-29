<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\HttpClient;

class HttpClientResponse
{
    /** @var int */
    private $statusCode;

    /** @var string */
    private $headerList;

    /** @var string */
    private $responseBody;

    public function __construct(int $statusCode, string $headerList, string $responseBody)
    {
        $this->statusCode = $statusCode;
        $this->headerList = $headerList;
        $this->responseBody = $responseBody;
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
        foreach (explode("\r\n", $this->headerList) as $headerLine) {
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
