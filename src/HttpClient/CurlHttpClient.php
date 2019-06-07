<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\HttpClient;

use RuntimeException;

class CurlHttpClient implements HttpClientInterface
{
    /** @var resource */
    private $curlChannel;

    public function __construct()
    {
        if (false === $this->curlChannel = curl_init()) {
            throw new RuntimeException('unable to create cURL channel');
        }
    }

    public function __destruct()
    {
        curl_close($this->curlChannel);
    }

    /**
     * @param array<string,string> $requestHeaders
     */
    public function get(string $requestUri, array $requestHeaders = []): Response
    {
        return $this->exec(
            [
                CURLOPT_URL => $requestUri,
            ],
            $requestHeaders
        );
    }

    /**
     * @param array<string,string> $requestHeaders
     */
    private function exec(array $curlOptions, array $requestHeaders): Response
    {
        // reset all cURL options
        curl_reset($this->curlChannel);

        /** @var array<string,string> */
        $headerList = [];

        $defaultCurlOptions = [
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true, // follow redirects
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => /**
             * @param resource $curlChannel
             */
            function ($curlChannel, string $headerData) use (&$headerList): int {
                if (false !== strpos($headerData, ':')) {
                    list($key, $value) = explode(':', $headerData, 2);
                    $headerList[trim($key)] = trim($value);
                }

                return \strlen($headerData);
            },
        ];

        if (0 !== \count($requestHeaders)) {
            $curlRequestHeaders = [];
            foreach ($requestHeaders as $k => $v) {
                $curlRequestHeaders[] = sprintf('%s: %s', $k, $v);
            }
            $defaultCurlOptions[CURLOPT_HTTPHEADER] = $curlRequestHeaders;
        }

        if (false === curl_setopt_array($this->curlChannel, $curlOptions + $defaultCurlOptions)) {
            throw new RuntimeException('unable to set cURL options');
        }

        $responseData = curl_exec($this->curlChannel);
        if (!\is_string($responseData)) {
            $curlError = curl_error($this->curlChannel);
            throw new RuntimeException(sprintf('failure performing the HTTP request: "%s"', $curlError));
        }

        return new Response(
            curl_getinfo($this->curlChannel, CURLINFO_HTTP_CODE),
            $responseData,
            $headerList
        );
    }
}
