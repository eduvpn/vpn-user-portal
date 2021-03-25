<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\HttpClient;

use LC\Portal\Exception\JsonException;
use LC\Portal\HttpClient\Exception\ApiException;
use LC\Portal\HttpClient\Exception\HttpClientException;
use LC\Portal\Json;

/**
 * @deprecated
 */
class ServerClient
{
    /** @var HttpClientInterface */
    private $httpClient;

    /** @var string */
    private $baseUri;

    /**
     * @param string $baseUri
     */
    public function __construct(HttpClientInterface $httpClient, $baseUri)
    {
        $this->httpClient = $httpClient;
        $this->baseUri = $baseUri;
    }

    /**
     * @param string $requestPath
     *
     * @return array
     */
    public function getRequireArray($requestPath, array $getData = [])
    {
        $responseData = $this->get($requestPath, $getData);

        return self::requireArray($responseData);
    }

    /**
     * @param string $requestPath
     *
     * @return false|array
     */
    public function getRequireArrayOrFalse($requestPath, array $getData = [])
    {
        $responseData = $this->get($requestPath, $getData);

        return self::requireArrayOrFalse($responseData);
    }

    /**
     * @param string $requestPath
     *
     * @return string
     */
    public function getRequireString($requestPath, array $getData = [])
    {
        $responseData = $this->get($requestPath, $getData);

        return self::requireString($responseData);
    }

    /**
     * @param string $requestPath
     *
     * @return bool
     */
    public function getRequireBool($requestPath, array $getData = [])
    {
        $responseData = $this->get($requestPath, $getData);

        return self::requireBool($responseData);
    }

    /**
     * @param string $requestPath
     *
     * @return int
     */
    public function getRequireInt($requestPath, array $getData = [])
    {
        $responseData = $this->get($requestPath, $getData);

        return self::requireInt($responseData);
    }

    /**
     * @param string $requestPath
     *
     * @return bool|string|array|int|null
     */
    public function get($requestPath, array $getData = [])
    {
        $requestUri = sprintf('%s/%s', $this->baseUri, $requestPath);

        return $this->responseHandler(
            'GET',
            $requestPath,
            $this->httpClient->get($requestUri, $getData)
        );
    }

    /**
     * @param string $requestPath
     *
     * @return array
     */
    public function postRequireArray($requestPath, array $postData)
    {
        $responseData = $this->post($requestPath, $postData);

        return self::requireArray($responseData);
    }

    /**
     * @param string $requestPath
     *
     * @return string
     */
    public function postRequireString($requestPath, array $postData)
    {
        $responseData = $this->post($requestPath, $postData);

        return self::requireString($responseData);
    }

    /**
     * @param string $requestPath
     *
     * @return bool
     */
    public function postRequireBool($requestPath, array $postData)
    {
        $responseData = $this->post($requestPath, $postData);

        return self::requireBool($responseData);
    }

    /**
     * @param string $requestPath
     *
     * @return bool|string|array|int|null
     */
    public function post($requestPath, array $postData)
    {
        $requestUri = sprintf('%s/%s', $this->baseUri, $requestPath);

        return $this->responseHandler(
            'POST',
            $requestPath,
            $this->httpClient->post($requestUri, [], $postData)
        );
    }

    /**
     * @param string $requestPath
     *
     * @return bool|string|array|int
     */
    private function getRequireData($requestPath, array $getData = [])
    {
        $responseData = $this->get($requestPath, $getData);

        return self::requireNotNull($responseData);
    }

    /**
     * @param string $requestPath
     *
     * @return bool|string|array|int
     */
    private function postRequireData($requestPath, array $postData)
    {
        $responseData = $this->post($requestPath, $postData);

        return self::requireNotNull($responseData);
    }

    /**
     * @param string $requestMethod
     * @param string $requestPath
     *
     * @return bool|string|array|int|null
     */
    private function responseHandler($requestMethod, $requestPath, HttpClientResponse $clientResponse)
    {
        $statusCode = $clientResponse->getCode();
        $responseString = $clientResponse->getBody();
        try {
            $responseData = Json::decode($responseString);
            $this->validateClientResponse($requestMethod, $requestPath, $statusCode, $responseData);

            if (400 <= $statusCode) {
                // either we sent an incorrect request, or there is a server error
                throw new HttpClientException(sprintf('[%d] %s "%s/%s": %s', $statusCode, $requestMethod, $this->baseUri, $requestPath, $responseData['error']));
            }

            // the request was correct, and there was not a server error
            if ($responseData[$requestPath]['ok']) {
                // our request was handled correctly
                if (!\array_key_exists('data', $responseData[$requestPath])) {
                    return null;
                }

                return $responseData[$requestPath]['data'];
            }

            // our request was not handled correctly, something went wrong...
            throw new ApiException($responseData[$requestPath]['error']);
        } catch (JsonException $e) {
            // unable to parse the JSON
            throw new HttpClientException(sprintf('[%d] %s "%s/%s": %s', $statusCode, $requestMethod, $this->baseUri, $requestPath, $e->getMessage()));
        }
    }

    /**
     * @param string $requestMethod
     * @param string $requestPath
     * @param int    $statusCode
     */
    private function validateClientResponse($requestMethod, $requestPath, $statusCode, array $responseData): void
    {
        if (400 <= $statusCode) {
            // if status code is 4xx or 5xx it MUST have an 'error' field
            if (!\array_key_exists('error', $responseData)) {
                throw new HttpClientException(sprintf('[%d] %s "%s/%s": responseData MUST contain "error" field', $statusCode, $requestMethod, $this->baseUri, $requestPath));
            }

            return;
        }

        if (!\array_key_exists($requestPath, $responseData)) {
            throw new HttpClientException(sprintf('[%d] %s "%s/%s": responseData MUST contain "%s" field', $statusCode, $requestMethod, $this->baseUri, $requestPath, $requestPath));
        }

        if (!\array_key_exists('ok', $responseData[$requestPath])) {
            throw new HttpClientException(sprintf('[%d] %s "%s/%s": responseData MUST contain "%s/ok" field', $statusCode, $requestMethod, $this->baseUri, $requestPath, $requestPath));
        }

        if (!$responseData[$requestPath]['ok']) {
            // not OK response, MUST contain error field
            if (!\array_key_exists('error', $responseData[$requestPath])) {
                throw new HttpClientException(sprintf('[%d] %s "%s/%s": responseData MUST contain "%s/error" field', $statusCode, $requestMethod, $this->baseUri, $requestPath, $requestPath));
            }
        }
    }

    /**
     * @param mixed $in
     *
     * @return string
     */
    private static function requireString($in)
    {
        if (!\is_string($in)) {
            throw new HttpClientException('response "data" field MUST be string');
        }

        return $in;
    }

    /**
     * @param mixed $in
     *
     * @return bool
     */
    private static function requireBool($in)
    {
        if (!\is_bool($in)) {
            throw new HttpClientException('response "data" field MUST be bool');
        }

        return $in;
    }

    /**
     * @param mixed $in
     *
     * @return array
     */
    private static function requireArray($in)
    {
        if (!\is_array($in)) {
            throw new HttpClientException('response "data" field MUST be array');
        }

        return $in;
    }

    /**
     * @param mixed $in
     *
     * @return false|array
     */
    private static function requireArrayOrFalse($in)
    {
        if (!\is_array($in) && false !== $in) {
            throw new HttpClientException('response "data" field MUST be array|false');
        }

        return $in;
    }

    /**
     * @param mixed $in
     *
     * @return int
     */
    private static function requireInt($in)
    {
        if (!\is_int($in)) {
            throw new HttpClientException('response "data" field MUST be int');
        }

        return $in;
    }

    /**
     * @param mixed $in
     *
     * @return bool|string|array|int
     */
    private static function requireNotNull($in)
    {
        if (null === $in) {
            throw new HttpClientException('response "data" field MUST exist');
        }

        return $in;
    }
}
