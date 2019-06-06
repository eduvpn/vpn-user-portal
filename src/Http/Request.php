<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Exception\HttpException;

class Request
{
    /** @var array */
    private $serverData;

    /** @var array<string,string> */
    private $getData;

    /** @var array<string,string> */
    private $postData;

    /**
     * @param array $serverData
     * @param array $getData
     * @param array $postData
     */
    public function __construct(array $serverData, array $getData = [], array $postData = [])
    {
        $this->serverData = $serverData;

        // make sure getData and postData are array<string,string>
        foreach ([$getData, $postData] as $keyValueList) {
            foreach ($keyValueList as $k => $v) {
                if (!\is_string($k) || !\is_string($v)) {
                    throw new HttpException('GET/POST parameter key and value MUST be of type "string"', 400);
                }
            }
        }
        $this->getData = $getData;
        $this->postData = $postData;
    }

    /**
     * URI = scheme:[//authority]path[?query][#fragment]
     * authority = [userinfo@]host[:port].
     *
     * @see https://en.wikipedia.org/wiki/Uniform_Resource_Identifier#Generic_syntax
     */
    public function getAuthority(): string
    {
        // scheme
        $requestScheme = $this->optionalHeader('REQUEST_SCHEME') ?? 'http';

        // server_name
        $serverName = $this->requireHeader('SERVER_NAME');

        // port
        $serverPort = (int) $this->requireHeader('SERVER_PORT');

        $usePort = false;
        if ('https' === $requestScheme && 443 !== $serverPort) {
            $usePort = true;
        }
        if ('http' === $requestScheme && 80 !== $serverPort) {
            $usePort = true;
        }

        if ($usePort) {
            return sprintf('%s://%s:%d', $requestScheme, $serverName, $serverPort);
        }

        return sprintf('%s://%s', $requestScheme, $serverName);
    }

    public function getUri(): string
    {
        return sprintf('%s%s', $this->getAuthority(), $this->requireHeader('REQUEST_URI'));
    }

    public function getRoot(): string
    {
        $rootDir = \dirname($this->requireHeader('SCRIPT_NAME'));
        if ('/' !== $rootDir) {
            return sprintf('%s/', $rootDir);
        }

        return $rootDir;
    }

    public function getRootUri(): string
    {
        return sprintf('%s%s', $this->getAuthority(), $this->getRoot());
    }

    public function getRequestMethod(): string
    {
        return $this->requireHeader('REQUEST_METHOD');
    }

    public function getServerName(): string
    {
        return $this->requireHeader('SERVER_NAME');
    }

    public function isBrowser(): bool
    {
        if (null === $httpAccept = $this->optionalHeader('HTTP_ACCEPT')) {
            return false;
        }

        return false !== mb_strpos($httpAccept, 'text/html');
    }

    public function getPathInfo(): string
    {
        $requestUri = $this->requireHeader('REQUEST_URI');
        $scriptName = $this->requireHeader('SCRIPT_NAME');

        // remove the query string
        if (false !== $pos = mb_strpos($requestUri, '?')) {
            $requestUri = mb_substr($requestUri, 0, $pos);
        }

        // if requestUri === scriptName
        if ($this->requireHeader('REQUEST_URI') === $scriptName) {
            return '/';
        }

        // remove script_name (if it is part of request_uri)
        if (0 === mb_strpos($requestUri, $scriptName)) {
            return substr($requestUri, mb_strlen($scriptName));
        }

        // remove the root
        if ('/' !== $this->getRoot()) {
            return mb_substr($requestUri, mb_strlen($this->getRoot()) - 1);
        }

        return $requestUri;
    }

    /**
     * Get the "raw" not-urldecoded query string.
     */
    public function getQueryString(): string
    {
        return $this->optionalHeader('QUERY_STRING') ?? '';
    }

    /**
     * @return array<string,string>
     */
    public function getQueryParameters(): array
    {
        return $this->getData;
    }

    public function requireQueryParameter(string $getKey): string
    {
        if (!\array_key_exists($getKey, $this->getData)) {
            throw new HttpException(sprintf('missing GET parameter "%s"', $getKey), 400);
        }

        return $this->getData[$getKey];
    }

    public function optionalQueryParameter(string $getKey): ?string
    {
        if (!\array_key_exists($getKey, $this->getData)) {
            return null;
        }

        return $this->getData[$getKey];
    }

    /**
     * @return array<string,string>
     */
    public function getPostParameters(): array
    {
        return $this->postData;
    }

    public function requirePostParameter(string $postKey): string
    {
        if (!\array_key_exists($postKey, $this->postData)) {
            throw new HttpException(sprintf('missing POST parameter "%s"', $postKey), 400);
        }

        return $this->postData[$postKey];
    }

    public function optionalPostParameter(string $postKey): ?string
    {
        if (!\array_key_exists($postKey, $this->postData)) {
            return null;
        }

        return $this->postData[$postKey];
    }

    public function requireHeader(string $headerKey): string
    {
        if (!\array_key_exists($headerKey, $this->serverData)) {
            throw new HttpException(sprintf('missing request header "%s"', $headerKey), 400);
        }

        return $this->serverData[$headerKey];
    }

    public function optionalHeader(string $headerKey): ?string
    {
        if (!\array_key_exists($headerKey, $this->serverData)) {
            return null;
        }

        return $this->serverData[$headerKey];
    }
}
