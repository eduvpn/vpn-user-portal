<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\HttpClient;

class HttpClientRequest
{
    private string $requestMethod;
    private string $requestUrl;

    /** @var array<string,array<string>|string> */
    private array $queryParameters;

    /** @var array<string,array<string>|string> */
    private array $postParameters;

    /** @var array<string,string> */
    private array $requestHeaders;

    /**
     * @param array<string,array<string>|string> $queryParameters
     * @param array<string,array<string>|string> $postParameters
     * @param array<string,string>               $requestHeaders
     */
    public function __construct(string $requestMethod, string $requestUrl, array $queryParameters = [], array $postParameters = [], array $requestHeaders = [])
    {
        $this->requestMethod = $requestMethod;
        $this->requestUrl = $requestUrl;
        $this->queryParameters = $queryParameters;
        $this->postParameters = $postParameters;
        $this->requestHeaders = $requestHeaders;
    }

    public function __toString(): string
    {
        if ('GET' === $this->requestMethod()) {
            return 'GET'.' '.$this->requestUrl().' ['.implode(',', $this->requestHeaders).']';
        }

        if ('POST' === $this->requestMethod()) {
            return 'POST'.' '.$this->requestUrl().' ['.$this->postParameters().'], ['.implode(',', $this->requestHeaders).']';
        }

        return 'unsupported request method';
    }

    public function requestMethod(): string
    {
        return $this->requestMethod;
    }

    public function requestUrl(): string
    {
        $requestUrl = $this->requestUrl;
        if (0 !== \count($this->queryParameters)) {
            // add (additional) query parameters to request URL
            $qSep = false === strpos($requestUrl, '?') ? '?' : '&';
            $requestUrl .= $qSep.$this->queryParameters();
        }

        return $requestUrl;
    }

    public function queryParameters(): string
    {
        return self::buildQuery($this->queryParameters);
    }

    public function postParameters(): string
    {
        return self::buildQuery($this->postParameters);
    }

    // XXX sync with vpn-server-node, we need to 'serialize' them!
    public function requestHeaders(): array
    {
        return $this->requestHeaders;
    }

    /**
     * Properly encode HTTP (POST) query parameters while also supporting
     * duplicate key names. PHP's built in http_build_query uses weird key[]
     * syntax that I am not a big fan of.
     *
     * @param array<string,array<string>|string> $queryParameters
     */
    private static function buildQuery(array $queryParameters): string
    {
        $qParts = [];
        foreach ($queryParameters as $k => $v) {
            if (\is_string($v)) {
                $qParts[] = urlencode($k).'='.urlencode($v);
            }
            if (\is_array($v)) {
                foreach ($v as $w) {
                    $qParts[] = urlencode($k).'='.urlencode($w);
                }
            }
        }

        return implode('&', $qParts);
    }
}
