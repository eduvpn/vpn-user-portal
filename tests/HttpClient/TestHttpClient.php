<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\HttpClient;

use LC\Portal\HttpClient\HttpClientInterface;
use LC\Portal\HttpClient\HttpClientResponse;
use RuntimeException;

class TestHttpClient implements HttpClientInterface
{
    /**
     * @param array<string,string> $queryParameters
     * @param array<string>        $requestHeaders
     */
    public function get(string $requestUrl, array $queryParameters, array $requestHeaders = []): HttpClientResponse
    {
        switch ($requestUrl) {
            case 'serverClient/foo':
                return new HttpClientResponse(200, '', self::wrap('foo', true));

            case 'serverClient/foo':
                if ('bar' === $queryParameters['foo']) {
                    return new HttpClientResponse(200, '', self::wrap('foo', true));
                }

                return new HttpClientResponse(400, '', self::wrapError('unexpected_request'));

            case 'serverClient/error':
                return new HttpClientResponse(400, '', json_encode(['error' => 'errorValue']));

            default:
                throw new RuntimeException(sprintf('unexpected requestUrl "%s"', $requestUrl));
        }
    }

    /**
     * @param array<string,string> $queryParameters
     * @param array<string,string> $postData
     * @param array<string>        $requestHeaders
     */
    public function post(string $requestUrl, array $queryParameters, array $postData, array $requestHeaders = []): HttpClientResponse
    {
        switch ($requestUrl) {
            case 'serverClient/foo':
                return new HttpClientResponse(200, '', self::wrap('foo', true));

            default:
                throw new RuntimeException(sprintf('unexpected requestUrl "%s"', $requestUrl));
        }
    }

    /**
     * @param array<string,string> $queryParameters
     * @param array<string>        $requestHeaders
     */
    public function postRaw(string $requestUrl, array $queryParameters, string $rawPost, array $requestHeaders = []): HttpClientResponse
    {
    }

    /**
     * @param mixed $key
     * @param mixed $responseData
     *
     * @return string
     */
    private static function wrap($key, $responseData)
    {
        return json_encode(
            [
                $key => [
                    'ok' => true,
                    'data' => $responseData,
                ],
            ]
        );
    }

    /**
     * @param mixed $key
     * @param mixed $errorMessage
     *
     * @return string
     */
    private static function wrapError($key, $errorMessage)
    {
        return json_encode(
            [
                $key => [
                    'ok' => false,
                    'error' => $errorMessage,
                ],
            ]
        );
    }
}
