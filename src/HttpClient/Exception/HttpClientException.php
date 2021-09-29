<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\HttpClient\Exception;

use Exception;
use LC\Portal\HttpClient\HttpClientRequest;
use LC\Portal\HttpClient\HttpClientResponse;
use Throwable;

class HttpClientException extends Exception
{
    private HttpClientRequest $httpClientRequest;
    private ?HttpClientResponse $httpClientResponse;

    public function __construct(HttpClientRequest $httpClientRequest, ?HttpClientResponse $httpClientResponse, string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->httpClientRequest = $httpClientRequest;
        $this->httpClientResponse = $httpClientResponse;
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
