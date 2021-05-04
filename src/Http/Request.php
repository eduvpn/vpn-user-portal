<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Exception\HttpException;

class Request
{
    /** @var array<string,mixed> */
    private $serverData;

    /** @var array<string,string|string[]> */
    private $getData;

    /** @var array<string,string|string[]> */
    private $postData;

    /**
     * @param array<string,mixed>           $serverData
     * @param array<string,string|string[]> $getData
     * @param array<string,string|string[]> $postData
     */
    public function __construct(array $serverData, array $getData = [], array $postData = [])
    {
        $this->serverData = $serverData;
        $this->getData = $getData;
        $this->postData = $postData;
    }

    public function getScheme(): string
    {
        if (null === $requestScheme = $this->optionalHeader('REQUEST_SCHEME')) {
            $requestScheme = 'http';
        }

        if (!\in_array($requestScheme, ['http', 'https'], true)) {
            throw new HttpException('unsupported "REQUEST_SCHEME"', 400);
        }

        return $requestScheme;
    }

    /**
     * URI = scheme:[//authority]path[?query][#fragment]
     * authority = [userinfo@]host[:port].
     *
     * @see https://en.wikipedia.org/wiki/Uniform_Resource_Identifier#Generic_syntax
     */
    public function getAuthority(): string
    {
        // we do not care about "userinfo"...
        $requestScheme = $this->getScheme();
        $serverName = $this->requireHeader('SERVER_NAME');
        $serverPort = (int) $this->requireHeader('SERVER_PORT');

        $usePort = false;
        if ('https' === $requestScheme && 443 !== $serverPort) {
            $usePort = true;
        }
        if ('http' === $requestScheme && 80 !== $serverPort) {
            $usePort = true;
        }

        if ($usePort) {
            return sprintf('%s:%d', $serverName, $serverPort);
        }

        return $serverName;
    }

    public function getUri(): string
    {
        $requestUri = $this->requireHeader('REQUEST_URI');

        return sprintf('%s://%s%s', $this->getScheme(), $this->getAuthority(), $requestUri);
    }

    public function getRoot(): string
    {
        if (null === $appRoot = $this->optionalHeader('VPN_APP_ROOT')) {
            return '/';
        }

        return $appRoot.'/';
    }

    public function getRootUri(): string
    {
        return sprintf('%s://%s%s', $this->getScheme(), $this->getAuthority(), $this->getRoot());
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
        // XXX can we not make this much simpler?
        // for example return always the stuff after VPN_APP_ROOT?

        // remove the query string
        $requestUri = $this->requireHeader('REQUEST_URI');
        if (false !== $pos = mb_strpos($requestUri, '?')) {
            $requestUri = mb_substr($requestUri, 0, $pos);
        }

        // if requestUri === scriptName
        $scriptName = $this->requireHeader('SCRIPT_NAME');
        if ($requestUri === $scriptName) {
            if (null === $appRoot = $this->optionalHeader('VPN_APP_ROOT')) {
                return '/';
            }

            // remove VPN_APP_ROOT from the REQUEST_URI
            return substr($requestUri, \strlen($appRoot));
        }

        // remove script_name (if it is part of request_uri
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
     * Return the "raw" query string.
     */
    public function getQueryString(): string
    {
        if (null === $queryString = $this->optionalHeader('QUERY_STRING')) {
            return '';
        }

        return $queryString;
    }

    public function requireQueryParameter(string $queryKey): string
    {
        if (!\array_key_exists($queryKey, $this->getData)) {
            throw new HttpException(sprintf('missing query parameter "%s"', $queryKey), 400);
        }
        if (!\is_string($this->getData[$queryKey])) {
            throw new HttpException(sprintf('value of query parameter "%s" MUST be string', $queryKey), 400);
        }

        return $this->getData[$queryKey];
    }

    public function optionalQueryParameter(string $queryKey): ?string
    {
        if (!\array_key_exists($queryKey, $this->getData)) {
            return null;
        }

        return $this->requireQueryParameter($queryKey);
    }

    public function requirePostParameter(string $postKey): string
    {
        if (!\array_key_exists($postKey, $this->postData)) {
            throw new HttpException(sprintf('missing post parameter "%s"', $postKey), 400);
        }
        if (!\is_string($this->postData[$postKey])) {
            throw new HttpException(sprintf('value of post parameter "%s" MUST be string', $postKey), 400);
        }

        return $this->postData[$postKey];
    }

    public function optionalPostParameter(string $postKey): ?string
    {
        if (!\array_key_exists($postKey, $this->postData)) {
            return null;
        }

        return $this->requirePostParameter($postKey);
    }

    /**
     * @return array<string,string>
     */
    public function getQueryParameters(): array
    {
        // make sure the GET parameter values are of type string
        $getData = [];
        foreach ($this->getData as $getKey => $getValue) {
            if (\is_string($getValue)) {
                $getData[$getKey] = $getValue;
            }
        }

        return $getData;
    }

    /**
     * @return array<string,string>
     */
    public function getPostParameters(): array
    {
        // make sure the POST parameter values are of type string
        $postData = [];
        foreach ($this->postData as $postKey => $postValue) {
            if (\is_string($postValue)) {
                $postData[$postKey] = $postValue;
            }
        }

        return $postData;
    }

    public function requireHeader(string $headerKey): string
    {
        if (!\array_key_exists($headerKey, $this->serverData)) {
            throw new HttpException(sprintf('missing request header "%s"', $headerKey), 400);
        }

        if (!\is_string($this->serverData[$headerKey])) {
            throw new HttpException(sprintf('value of request header "%s" MUST be string', $headerKey), 400);
        }

        return $this->serverData[$headerKey];
    }

    public function optionalHeader(string $headerKey): ?string
    {
        if (!\array_key_exists($headerKey, $this->serverData)) {
            return null;
        }

        return $this->requireHeader($headerKey);
    }
}
