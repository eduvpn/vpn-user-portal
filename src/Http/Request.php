<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Closure;
use RangeException;
use Vpn\Portal\Binary;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\Validator;

class Request
{
    /** @var array<string,mixed> */
    private $serverData;

    /** @var array<string,string|string[]> */
    private $getData;

    /** @var array<string,string|string[]> */
    private $postData;

    /** @var array<string,string> */
    private $cookieData;

    /**
     * @param array<string,mixed>  $serverData
     * @param array<mixed>         $getData
     * @param array<mixed>         $postData
     * @param array<string,string> $cookieData
     */
    public function __construct(array $serverData, array $getData, array $postData, array $cookieData)
    {
        $this->serverData = $serverData;
        $this->getData = $getData;
        $this->postData = $postData;
        $this->cookieData = $cookieData;
    }

    public static function createFromGlobals(): self
    {
        return new self(
            $_SERVER,
            $_GET,
            $_POST,
            $_COOKIE
        );
    }

    public function getScheme(): string
    {
        $requestScheme = 'http';
        if ('on' === $this->optionalHeader('HTTPS')) {
            $requestScheme = 'https';
        }
        if ('https' === $this->optionalHeader('REQUEST_SCHEME')) {
            $requestScheme = 'https';
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
        // we do NOT care about "userinfo"
        $requestScheme = $this->getScheme();
        $serverName = $this->requireHeader('SERVER_NAME');
        $serverPort = (int) $this->requireHeader('SERVER_PORT');

        if ('https' === $requestScheme && 443 === $serverPort) {
            return $serverName;
        }
        if ('http' === $requestScheme && 80 === $serverPort) {
            return $serverName;
        }

        return $serverName.':'.$serverPort;
    }

    public function getUri(): string
    {
        return $this->getScheme().'://'.$this->getAuthority().$this->requireHeader('REQUEST_URI');
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
        return $this->getScheme().'://'.$this->getAuthority().$this->getRoot();
    }

    public function getRequestMethod(): string
    {
        return $this->requireHeader('REQUEST_METHOD');
    }

    public function getServerName(): string
    {
        return $this->requireHeader('SERVER_NAME');
    }

    public function getOrigin(): string
    {
        return sprintf('%s://%s', $this->getScheme(), $this->getAuthority());
    }

    public function getPathInfo(): string
    {
        // if we have PATH_INFO available, use it
        if (null !== $pathInfo = $this->optionalHeader('PATH_INFO')) {
            return $pathInfo;
        }

        // if not, we have to reconstruct it
        $requestUri = $this->requireHeader('REQUEST_URI');

        // trim the query string (if any)
        if (false !== $queryStart = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $queryStart);
        }

        // remove the VPN_APP_ROOT (if any)
        if (null !== $appRoot = $this->optionalHeader('VPN_APP_ROOT')) {
            $requestUri = substr($requestUri, strlen($appRoot));
        }

        return $requestUri;
    }

    /**
     * @param Closure(string):void $c
     */
    public function requireQueryParameter(string $queryKey, Closure $c): string
    {
        if (!\array_key_exists($queryKey, $this->getData)) {
            throw new HttpException(sprintf('missing query parameter "%s"', $queryKey), 400);
        }
        if (!\is_string($this->getData[$queryKey])) {
            throw new HttpException(sprintf('value of query parameter "%s" MUST be string', $queryKey), 400);
        }

        try {
            $c($this->getData[$queryKey]);
        } catch (RangeException $e) {
            throw new HttpException(sprintf('invalid value for "%s"', $queryKey), 400);
        }

        return $this->getData[$queryKey];
    }

    /**
     * @param Closure(string):void $c
     */
    public function optionalQueryParameter(string $queryKey, Closure $c): ?string
    {
        if (!\array_key_exists($queryKey, $this->getData)) {
            return null;
        }

        return $this->requireQueryParameter($queryKey, $c);
    }

    /**
     * @param Closure(array<string>):void $c
     */
    public function optionalArrayPostParameter(string $postKey, Closure $c): array
    {
        if (!\array_key_exists($postKey, $this->postData)) {
            return [];
        }
        $postValue = $this->postData[$postKey];
        if (\is_string($postValue)) {
            $postValue = [$postValue];
        }

        try {
            $c($postValue);
        } catch (RangeException $e) {
            throw new HttpException(sprintf('invalid value for "%s"', $postKey), 400);
        }

        return $postValue;
    }

    /**
     * @param Closure(string):void $c
     */
    public function requirePostParameter(string $postKey, Closure $c): string
    {
        if (!\array_key_exists($postKey, $this->postData)) {
            throw new HttpException(sprintf('missing post parameter "%s"', $postKey), 400);
        }
        if (!\is_string($this->postData[$postKey])) {
            throw new HttpException(sprintf('value of post parameter "%s" MUST be string', $postKey), 400);
        }

        try {
            $c($this->postData[$postKey]);
        } catch (RangeException $e) {
            throw new HttpException(sprintf('invalid value for "%s"', $postKey), 400);
        }

        return $this->postData[$postKey];
    }

    /**
     * @param Closure(string):void $c
     */
    public function optionalPostParameter(string $postKey, Closure $c): ?string
    {
        if (!\array_key_exists($postKey, $this->postData)) {
            return null;
        }

        return $this->requirePostParameter($postKey, $c);
    }

    /**
     * @param Closure(string):void $c
     */
    public function getCookie(string $cookieKey, Closure $c): ?string
    {
        if (!\array_key_exists($cookieKey, $this->cookieData)) {
            return null;
        }

        try {
            $c($this->cookieData[$cookieKey]);

            return $this->cookieData[$cookieKey];
        } catch (RangeException $e) {
            // when a cookie value is malformed, we consider it not set at all
            return null;
        }
    }

    // XXX introduce validator function as well?!
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

    // XXX introduce validator function as well?!
    public function optionalHeader(string $headerKey): ?string
    {
        if (!\array_key_exists($headerKey, $this->serverData)) {
            return null;
        }

        return $this->requireHeader($headerKey);
    }

    /**
     * If the HTTP_REFERER header is set, verify it and return it.
     */
    public function optionalReferrer(): ?string
    {
        if (null === $referrerHeaderValue = $this->optionalHeader('HTTP_REFERER')) {
            return null;
        }

        try {
            Validator::matchesOrigin($this->getOrigin(), $referrerHeaderValue);

            return $referrerHeaderValue;
        } catch (RangeException $e) {
            throw new HttpException('unexpected HTTP_REFERER', 400);
        }
    }

    /**
     * Verify the HTTP_REFERER and return it.
     */
    public function requireReferrer(): string
    {
        if (null === $referrerHeaderValue = $this->optionalReferrer()) {
            throw new HttpException('missing HTTP_REFERER', 400);
        }

        return $referrerHeaderValue;
    }
}
