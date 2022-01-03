<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests\Http;

use RuntimeException;
use Vpn\Portal\HttpClient\HttpClientInterface;
use Vpn\Portal\HttpClient\HttpClientResponse;

class TestHttpClient implements HttpClientInterface
{
    /**
     * @param string               $requestUrl
     * @param array<string,string> $queryParameters
     * @param array<string>        $requestHeaders
     *
     * @return \Vpn\Portal\HttpClient\HttpClientResponse
     */
    public function get($requestUrl, array $queryParameters, array $requestHeaders = [])
    {
        switch ($requestUrl) {
            case 'serverClient/has_totp_secret':
                if ('foo' === $queryParameters['user_id']) {
                    return new HttpClientResponse(200, [], self::wrap('has_totp_secret', true));
                }
                if ('bar' === $queryParameters['user_id']) {
                    return new HttpClientResponse(200, [], self::wrap('has_totp_secret', false));
                }

                return new HttpClientResponse(400, [], self::wrapError('unexpected_request'));

            default:
                throw new RuntimeException(sprintf('unexpected requestUrl "%s"', $requestUrl));
        }
    }

    /**
     * @param string               $requestUrl
     * @param array<string,string> $queryParameters
     * @param array<string,string> $postData
     * @param array<string>        $requestHeaders
     *
     * @return \Vpn\Portal\HttpClient\HttpClientResponse
     */
    public function post($requestUrl, array $queryParameters, array $postData, array $requestHeaders = [])
    {
        switch ($requestUrl) {
            case 'serverClient/verify_totp_key':
                if ('foo' === $postData['user_id']) {
                    return new HttpClientResponse(200, [], self::wrap('verify_totp_key', true));
                }

                return new HttpClientResponse(200, [], self::wrapError('verify_totp_key', 'invalid OTP key'));

            default:
                throw new RuntimeException(sprintf('unexpected requestUrl "%s"', $requestUrl));
        }
    }

    /**
     * @param string               $requestUrl
     * @param array<string,string> $queryParameters
     * @param string               $rawPost
     * @param array<string>        $requestHeaders
     *
     * @return HttpClientResponse
     */
    public function postRaw($requestUrl, array $queryParameters, $rawPost, array $requestHeaders = [])
    {
    }

    /**
     * @param string $key
     * @param mixed  $responseData
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
     * @param string $key
     * @param mixed  $errorMessage
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
