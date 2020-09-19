<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Federation;

use LC\Portal\Federation\Exception\HttpClientException;
use RuntimeException;

class CurlHttpClient implements HttpClientInterface
{
    /**
     * @param string        $requestUrl
     * @param array<string> $requestHeaders
     *
     * @return HttpClientResponse
     */
    public function get($requestUrl, array $requestHeaders)
    {
        if (false === $curlChannel = curl_init()) {
            throw new RuntimeException('unable to create cURL channel');
        }

        $headerList = '';
        $curlOptions = [
            CURLOPT_URL => $requestUrl,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true, // follow redirects
            CURLOPT_MAXREDIRS => 3,         // follow up to 3 redirects
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 90,
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
