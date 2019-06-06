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

    /**
     * @param int    $statusCode
     * @param string $contentType
     */
    public function __construct($statusCode = 200, $contentType = 'text/plain')
    {
        $this->statusCode = $statusCode;
        $this->headers = [
            'Content-Type' => $contentType,
        ];
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return void
     */
    public function addHeader($key, $value)
    {
        $this->headers[$key] = $value;
    }

    /**
     * @param string $key
     *
     * @return string|null
     */
    public function getHeader($key)
    {
        if (\array_key_exists($key, $this->headers)) {
            return $this->headers[$key];
        }

        return null;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param string $body
     *
     * @return void
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param array $responseData
     *
     * @return self
     */
    public static function import(array $responseData)
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

    /**
     * @return void
     */
    public function send()
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
