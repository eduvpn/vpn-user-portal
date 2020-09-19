<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Federation;

class HttpClientResponse
{
    /** @var int */
    private $responseCode;

    /** @var string */
    private $headerList;

    /** @var string */
    private $responseBody;

    /**
     * @param int    $responseCode
     * @param string $headerList
     * @param string $responseBody
     */
    public function __construct($responseCode, $headerList, $responseBody)
    {
        $this->responseCode = $responseCode;
        $this->headerList = $headerList;
        $this->responseBody = $responseBody;
    }

    /**
     * @return int
     */
    public function getCode()
    {
        return $this->responseCode;
    }

    /**
     * We loop over all available headers and return the value of the first
     * matching header key. If multiple headers with the same name are present
     * the next ones are ignored!
     *
     * @param string $headerKey
     *
     * @return string|null
     */
    public function getHeader($headerKey)
    {
        foreach (explode("\r\n", $this->headerList) as $headerLine) {
            if (false === strpos($headerLine, ':')) {
                continue;
            }
            list($k, $v) = explode(':', $headerLine, 2);
            if (strtolower(trim($headerKey)) === strtolower(trim($k))) {
                return trim($v);
            }
        }

        return null;
    }

    /**
     * @return string
     */
    public function getHeaderList()
    {
        return $this->headerList;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->responseBody;
    }
}
