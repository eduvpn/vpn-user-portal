<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\HttpClient\Exception;

use Exception;
use Throwable;
use Vpn\Portal\HttpClient\HttpClientRequest;
use Vpn\Portal\HttpClient\HttpClientResponse;

class HttpClientException extends Exception
{
    private HttpClientRequest $httpClientRequest;
    private ?HttpClientResponse $httpClientResponse;

    public function __construct(HttpClientRequest $httpClientRequest, ?HttpClientResponse $httpClientResponse, string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->httpClientRequest = $httpClientRequest;
        $this->httpClientResponse = $httpClientResponse;
    }

    public function __toString(): string
    {
        return $this->message.' {'.$this->httpClientRequest.($this->httpClientResponse ?? '').'}';
    }

    public function httpClientRequest(): HttpClientRequest
    {
        return $this->httpClientRequest;
    }

    public function httpClientResponse(): ?HttpClientResponse
    {
        return $this->httpClientResponse;
    }
}
