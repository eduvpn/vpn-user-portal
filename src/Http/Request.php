<?php

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
    /** @var array<string,string> */
    private $serverData;

    /** @var array<string,string> */
    private $getData;

    /** @var array<string,string> */
    private $postData;

    /**
     * @param array<string,string> $serverData
     * @param array                $getData
     * @param array                $postData
     */
    public function __construct(array $serverData, array $getData = [], array $postData = [])
    {
        // make sure serverData, getData and postData are array<string,string>
        foreach ([$serverData, $getData, $postData] as $keyValueList) {
            foreach ($keyValueList as $k => $v) {
                if (!\is_string($k) || !\is_string($v)) {
                    throw new HttpException('SERVER/GET/POST parameter key and value MUST be of type "string"', 400);
                }
            }
        }

        $requiredHeaders = [
            'REQUEST_METHOD',
            'SERVER_NAME',
            'SERVER_PORT',
            'REQUEST_URI',
            'SCRIPT_NAME',
        ];

        foreach ($requiredHeaders as $key) {
            if (!\array_key_exists($key, $serverData)) {
                // this indicates something wrong with the interaction between
                // the web server and PHP, these headers MUST always be available
                throw new HttpException(sprintf('missing header "%s"', $key), 500);
            }
        }
        $this->serverData = $serverData;
        $this->getData = $getData;
        $this->postData = $postData;
    }

    /**
     * @return string
     */
    public function getAuthority()
    {
        // scheme
        if (!\array_key_exists('REQUEST_SCHEME', $this->serverData)) {
            $requestScheme = 'http';
        } else {
            $requestScheme = $this->serverData['REQUEST_SCHEME'];
        }

        // server_name
        $serverName = $this->serverData['SERVER_NAME'];

        // port
        $serverPort = (int) $this->serverData['SERVER_PORT'];

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

    /**
     * @return string
     */
    public function getUri()
    {
        $requestUri = $this->serverData['REQUEST_URI'];

        return sprintf('%s%s', $this->getAuthority(), $requestUri);
    }

    /**
     * @return string
     */
    public function getRoot()
    {
        $rootDir = \dirname($this->serverData['SCRIPT_NAME']);
        if ('/' !== $rootDir) {
            return sprintf('%s/', $rootDir);
        }

        return $rootDir;
    }

    /**
     * @return string
     */
    public function getRootUri()
    {
        return sprintf('%s%s', $this->getAuthority(), $this->getRoot());
    }

    /**
     * @return string
     */
    public function getRequestMethod()
    {
        return $this->serverData['REQUEST_METHOD'];
    }

    /**
     * @return string
     */
    public function getServerName()
    {
        return $this->serverData['SERVER_NAME'];
    }

    /**
     * @return bool
     */
    public function isBrowser()
    {
        if (!\array_key_exists('HTTP_ACCEPT', $this->serverData)) {
            return false;
        }

        return false !== mb_strpos($this->serverData['HTTP_ACCEPT'], 'text/html');
    }

    /**
     * @return string
     */
    public function getPathInfo()
    {
        // remove the query string
        $requestUri = $this->serverData['REQUEST_URI'];
        if (false !== $pos = mb_strpos($requestUri, '?')) {
            $requestUri = mb_substr($requestUri, 0, $pos);
        }

        // if requestUri === scriptName
        if ($this->serverData['REQUEST_URI'] === $this->serverData['SCRIPT_NAME']) {
            return '/';
        }

        // remove script_name (if it is part of request_uri
        if (0 === mb_strpos($requestUri, $this->serverData['SCRIPT_NAME'])) {
            return substr($requestUri, mb_strlen($this->serverData['SCRIPT_NAME']));
        }

        // remove the root
        if ('/' !== $this->getRoot()) {
            return mb_substr($requestUri, mb_strlen($this->getRoot()) - 1);
        }

        return $requestUri;
    }

    /**
     * Return the "raw" query string.
     *
     * @return string
     */
    public function getQueryString()
    {
        if (!\array_key_exists('QUERY_STRING', $this->serverData)) {
            return '';
        }

        return $this->serverData['QUERY_STRING'];
    }

    /**
     * @return array<string,string>
     */
    public function getQueryParameters()
    {
        return $this->getData;
    }

    /**
     * @param string $getKey
     *
     * @return string
     */
    public function requireQueryParameter($getKey)
    {
        if (!\array_key_exists($getKey, $this->getData)) {
            throw new HttpException(sprintf('missing GET parameter "%s"', $getKey), 400);
        }

        return $this->getData[$getKey];
    }

    /**
     * @param string $getKey
     *
     * @return string|null
     */
    public function optionalQueryParameter($getKey)
    {
        if (!\array_key_exists($getKey, $this->getData)) {
            return null;
        }

        return $this->getData[$getKey];
    }

    /**
     * @return array<string,string>
     */
    public function getPostParameters()
    {
        return $this->postData;
    }

    /**
     * @param string $postKey
     *
     * @return string
     */
    public function requirePostParameter($postKey)
    {
        if (!\array_key_exists($postKey, $this->postData)) {
            throw new HttpException(sprintf('missing POST parameter "%s"', $postKey), 400);
        }

        return $this->postData[$postKey];
    }

    /**
     * @param string $postKey
     *
     * @return string|null
     */
    public function optionalPostParameter($postKey)
    {
        if (!\array_key_exists($postKey, $this->postData)) {
            return null;
        }

        return $this->postData[$postKey];
    }

    /**
     * @param string $headerKey
     *
     * @return string
     */
    public function requireHeader($headerKey)
    {
        if (!\array_key_exists($headerKey, $this->serverData)) {
            throw new HttpException(sprintf('missing request header "%s"', $headerKey), 400);
        }

        return $this->serverData[$headerKey];
    }

    /**
     * @param string $headerKey
     *
     * @return string|null
     */
    public function optionalHeader($headerKey)
    {
        if (!\array_key_exists($headerKey, $this->serverData)) {
            return null;
        }

        return $this->serverData[$headerKey];
    }
}
