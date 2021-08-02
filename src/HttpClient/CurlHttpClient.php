<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\HttpClient;

use LC\Portal\HttpClient\Exception\HttpClientException;
use RuntimeException;

class CurlHttpClient implements HttpClientInterface
{
    /** @var array<string> */
    private $requestHeaders = [];

    public function __construct(?string $authToken = null)
    {
        if (null !== $authToken) {
            $this->requestHeaders[] = 'Authorization: Bearer '.$authToken;
        }
    }

    /**
     * @param array<string,string> $queryParameters
     * @param array<string>        $requestHeaders
     */
    public function get(string $requestUrl, array $queryParameters, array $requestHeaders = []): HttpClientResponse
    {
        if (false === $curlChannel = curl_init()) {
            throw new RuntimeException('unable to create cURL channel');
        }

        if (0 !== \count($queryParameters)) {
            $qSep = false === strpos($requestUrl, '?') ? '?' : '&';
            $requestUrl .= $qSep.http_build_query($queryParameters);
        }

        $headerList = '';
        $curlOptions = [
            CURLOPT_URL => $requestUrl,
            CURLOPT_HTTPHEADER => array_merge($this->requestHeaders, $requestHeaders),
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION =>
            /**
             * @suppress PhanUnusedClosureParameter
             *
             * @param resource $curlChannel
             * @param string   $headerLine
             *
             * @return int
             */
            function ($curlChannel, $headerLine) use (&$headerList) {
                $headerList .= $headerLine;

                return \strlen($headerLine);
            },
        ];

        if (false === curl_setopt_array($curlChannel, $curlOptions)) {
            throw new RuntimeException('unable to set cURL options');
        }

        $responseData = curl_exec($curlChannel);
        if (!\is_string($responseData)) {
            throw new HttpClientException(sprintf('failure performing the HTTP request: "%s"', curl_error($curlChannel)));
        }

        $responseCode = (int) curl_getinfo($curlChannel, CURLINFO_HTTP_CODE);
        curl_close($curlChannel);

        return new HttpClientResponse(
            $responseCode,
            $headerList,
            $responseData
        );
    }

    /**
     * @param array<string,string> $queryParameters
     * @param array<string,string> $postData
     * @param array<string>        $requestHeaders
     */
    public function post(string $requestUrl, array $queryParameters, array $postData, array $requestHeaders = []): HttpClientResponse
    {
        return $this->postRaw(
            $requestUrl,
            $queryParameters,
            http_build_query($postData),
            $requestHeaders
        );
    }

    /**
     * @param array<string,string> $queryParameters
     * @param array<string>        $requestHeaders
     */
    public function postRaw(string $requestUrl, array $queryParameters, string $rawPost, array $requestHeaders = []): HttpClientResponse
    {
        // XXX do not duplicate all GET code
        if (false === $curlChannel = curl_init()) {
            throw new RuntimeException('unable to create cURL channel');
        }

        if (0 !== \count($queryParameters)) {
            $qSep = false === strpos($requestUrl, '?') ? '?' : '&';
            $requestUrl .= $qSep.http_build_query($queryParameters);
        }

        $headerList = '';
        $curlOptions = [
            CURLOPT_URL => $requestUrl,
            CURLOPT_HTTPHEADER => array_merge($this->requestHeaders, $requestHeaders),
            CURLOPT_POSTFIELDS => $rawPost,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION =>
            /**
             * @suppress PhanUnusedClosureParameter
             *
             * @param resource $curlChannel
             * @param string   $headerLine
             *
             * @return int
             */
            function ($curlChannel, $headerLine) use (&$headerList) {
                $headerList .= $headerLine;

                return \strlen($headerLine);
            },
        ];

        if (false === curl_setopt_array($curlChannel, $curlOptions)) {
            throw new RuntimeException('unable to set cURL options');
        }

        $responseData = curl_exec($curlChannel);
        if (!\is_string($responseData)) {
            throw new HttpClientException(sprintf('failure performing the HTTP request: "%s"', curl_error($curlChannel)));
        }

        $responseCode = (int) curl_getinfo($curlChannel, CURLINFO_HTTP_CODE);
        curl_close($curlChannel);

        return new HttpClientResponse(
            $responseCode,
            $headerList,
            $responseData
        );
    }
}
