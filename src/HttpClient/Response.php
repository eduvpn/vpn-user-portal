<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\HttpClient;

use SURFnet\VPN\Common\Json;

class Response
{
    /** @var int */
    private $statusCode;

    /** @var string */
    private $responseBody;

    /** @var array */
    private $responseHeaders;

    /**
     * @param int    $statusCode
     * @param string $responseBody
     */
    public function __construct($statusCode, $responseBody, array $responseHeaders = [])
    {
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
        $this->responseHeaders = $responseHeaders;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $fmtHdrs = '';
        foreach ($this->responseHeaders as $k => $v) {
            $fmtHdrs .= \sprintf('%s: %s', $k, $v).PHP_EOL;
        }

        return \implode(
            PHP_EOL,
            [
                $this->statusCode,
                '',
                $fmtHdrs,
                '',
                $this->responseBody,
            ]
        );
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
        return $this->responseBody;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->responseHeaders;
    }

    /**
     * @param string $key
     *
     * @return string|null
     */
    public function getHeader($key)
    {
        foreach ($this->responseHeaders as $k => $v) {
            if (\strtoupper($key) === \strtoupper($k)) {
                return $v;
            }
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function json()
    {
        return Json::decode($this->responseBody);
    }

    /**
     * @return bool
     */
    public function isOkay()
    {
        return 200 <= $this->statusCode && 300 > $this->statusCode;
    }
}
