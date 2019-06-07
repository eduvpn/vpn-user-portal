<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

class Response
{
    /** @var int */
    private $statusCode;

    /** @var array */
    private $headers = [];

    /** @var string */
    private $body = '';

    public function __construct(int $statusCode = 200, string $contentType = 'text/plain')
    {
        $this->statusCode = $statusCode;
        $this->headers = [
            'Content-Type' => $contentType,
        ];
    }

    public function addHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    public function getHeader(string $key): ?string
    {
        if (\array_key_exists($key, $this->headers)) {
            return $this->headers[$key];
        }

        return null;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public static function import(array $responseData): self
    {
        $response = new self(
            $responseData['statusCode'],
            $responseData['responseHeaders']['Content-Type']
        );
        unset($responseData['responseHeaders']['Content-Type']);
        foreach ($responseData['responseHeaders'] as $key => $value) {
            $response->addHeader($key, $value);
        }
        $response->setBody($responseData['responseBody']);

        return $response;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        if ('' === $this->body) {
            unset($this->headers['Content-Type']);
        }
        foreach ($this->headers as $key => $value) {
            header(sprintf('%s: %s', $key, $value));
        }
        echo $this->body;
    }
}
