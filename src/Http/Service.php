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

class Service
{
    private ?AuthModuleInterface $authModule = null;

    private array $routeList = [];

    /** @var array<BeforeHookInterface> */
    private array $beforeHookList = [];

    /** @var array<string,array<string>> */
    private array $beforeAuthPathList = [
        'GET' => [],
        'POST' => [],
    ];

    public function setAuthModule(AuthModuleInterface $authModule): void
    {
        $this->authModule = $authModule;
    }

    public function addBeforeHook(BeforeHookInterface $beforeHook): void
    {
        $this->beforeHookList[] = $beforeHook;
    }

    public function get(string $pathInfo, callable $callback): void
    {
        $this->routeList[$pathInfo]['GET'] = $callback;
    }

    public function post(string $pathInfo, callable $callback): void
    {
        $this->routeList[$pathInfo]['POST'] = $callback;
    }

    public function postBeforeAuth(string $pathInfo, callable $callback): void
    {
        $this->routeList[$pathInfo]['POST'] = $callback;
        $this->beforeAuthPathList['POST'][] = $pathInfo;
    }

    public function addModule(ServiceModuleInterface $module): void
    {
        $module->init($this);
    }

    public function run(Request $request): Response
    {
        foreach ($this->beforeHookList as $beforeHook) {
            $hookResponse = $beforeHook->beforeAuth($request);
            // if we get back a Response object, return it immediately
            if ($hookResponse instanceof Response) {
                return $hookResponse;
            }
        }

        $requestMethod = $request->getRequestMethod();
        $pathInfo = $request->getPathInfo();

        // modules can use postBeforeAuth that require no authentication,
        // if the current request is for such a URL, execute the callback
        // immediately
        if (\array_key_exists($requestMethod, $this->beforeAuthPathList) && \in_array($pathInfo, $this->beforeAuthPathList[$requestMethod], true)) {
            return $this->routeList[$pathInfo][$requestMethod]($request);
        }

        if (null === $this->authModule) {
            throw new HttpException('authentication required, but no authentication module loaded', 500);
        }

        // make sure we are authenticated
        if (null === $userInfo = $this->authModule->userInfo($request)) {
            if (null !== $authResponse = $this->authModule->startAuth($request)) {
                return $authResponse;
            }

            throw new HttpException('unable to authenticate user', 401);
        }

        foreach ($this->beforeHookList as $beforeHook) {
            $hookResponse = $beforeHook->afterAuth($userInfo, $request);
            // if we get back a Response object, return it immediately
            if ($hookResponse instanceof Response) {
                return $hookResponse;
            }
        }

        if (!\array_key_exists($pathInfo, $this->routeList)) {
            throw new HttpException(sprintf('"%s" not found', $pathInfo), 404);
        }
        if (!\array_key_exists($requestMethod, $this->routeList[$pathInfo])) {
            throw new HttpException(sprintf('method "%s" not allowed', $requestMethod), 405, ['Allow' => implode(',', array_keys($this->routeList[$pathInfo]))]);
        }

        return $this->routeList[$pathInfo][$requestMethod]($userInfo, $request);
    }
}
