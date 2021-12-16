<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\HttpClient;

use RuntimeException;
use Vpn\Portal\Binary;
use Vpn\Portal\HttpClient\Exception\HttpClientException;

class CurlHttpClient implements HttpClientInterface
{
    private ?string $certPath;

    public function __construct(?string $certPath = null)
    {
        $this->certPath = $certPath;
    }

    public function send(HttpClientRequest $httpClientRequest): HttpClientResponse
    {
        if (false === $curlChannel = curl_init()) {
            throw new RuntimeException('unable to create cURL channel');
        }

        $headerList = '';
        $curlOptions = [
            CURLOPT_URL => $httpClientRequest->requestUrl(),
            CURLOPT_HTTPHEADER => $httpClientRequest->requestHeaders(),
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION =>
            /**
             * @param resource $curlChannel
             */
            function ($curlChannel, string $headerLine) use (&$headerList): int {
                $headerList .= $headerLine;

                return Binary::safeStrlen($headerLine);
            },
        ];

        if (null !== $this->certPath) {
            // configure for TLS client certificate authentication
            $curlOptions[CURLOPT_CAINFO] = $this->certPath.'/ca.crt';
            $curlOptions[CURLOPT_SSLCERT] = $this->certPath.'/vpn-daemon-client.crt';
            $curlOptions[CURLOPT_SSLKEY] = $this->certPath.'/vpn-daemon-client.key';
        }

        if ('POST' === $httpClientRequest->requestMethod()) {
            $curlOptions[CURLOPT_POSTFIELDS] = $httpClientRequest->postParameters();
        }

        if (false === curl_setopt_array($curlChannel, $curlOptions)) {
            throw new RuntimeException('unable to set cURL options');
        }

        $responseData = curl_exec($curlChannel);
        if (!\is_string($responseData)) {
            throw new HttpClientException($httpClientRequest, null, sprintf('failure performing the HTTP request: "%s"', curl_error($curlChannel)));
        }

        $responseCode = (int) curl_getinfo($curlChannel, CURLINFO_HTTP_CODE);
        curl_close($curlChannel);

        $httpClientResponse = new HttpClientResponse(
            $responseCode,
            $headerList,
            $responseData
        );

        if (!$httpClientResponse->isOkay()) {
            throw new HttpClientException($httpClientRequest, $httpClientResponse, 'unexpected HTTP response code ('.$responseCode.')');
        }

        return $httpClientResponse;
    }
}
