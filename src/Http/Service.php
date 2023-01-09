<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Closure;
use Vpn\Portal\Http\Exception\HttpException;

abstract class Service implements ServiceInterface
{
    protected AuthModuleInterface $authModule;

    /** @var array<string,array<string,Closure(Request):Response>> */
    protected array $beforeAuthRouteList = [];

    /** @var array<string,array<string,Closure(Request,UserInfo):Response>> */
    protected array $routeList = [];

    /** @var array<HookInterface> */
    protected array $hookList = [];

    public function __construct(AuthModuleInterface $authModule)
    {
        $this->authModule = $authModule;
        $this->authModule->init($this);
    }

    public function addHook(HookInterface $serviceHook): void
    {
        $this->hookList[] = $serviceHook;
    }

    /**
     * @param Closure(Request,UserInfo):Response $closure
     */
    public function get(string $pathInfo, Closure $closure): void
    {
        $this->routeList[$pathInfo]['GET'] = $closure;
    }

    /**
     * @param Closure(Request,UserInfo):Response $closure
     */
    public function post(string $pathInfo, Closure $closure): void
    {
        $this->routeList[$pathInfo]['POST'] = $closure;
    }

    /**
     * @param Closure(Request):Response $closure
     */
    public function postBeforeAuth(string $pathInfo, Closure $closure): void
    {
        $this->beforeAuthRouteList[$pathInfo]['POST'] = $closure;
    }

    public function addModule(ServiceModuleInterface $module): void
    {
        $module->init($this);
    }

    public function run(Request $request): Response
    {
        foreach ($this->hookList as $serviceHook) {
            $hookResponse = $serviceHook->beforeAuth($request);
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
        if (\array_key_exists($pathInfo, $this->beforeAuthRouteList) && \array_key_exists($requestMethod, $this->beforeAuthRouteList[$pathInfo])) {
            return $this->beforeAuthRouteList[$pathInfo][$requestMethod]($request);
        }

        // make sure we are authenticated
        if (null === $userInfo = $this->authModule->userInfo($request)) {
            if (null !== $authResponse = $this->authModule->startAuth($request)) {
                return $authResponse;
            }

            throw new HttpException('unable to authenticate user', 401);
        }

        foreach ($this->hookList as $serviceHook) {
            $hookResponse = $serviceHook->afterAuth($request, $userInfo);
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

        return $this->routeList[$pathInfo][$requestMethod]($request, $userInfo);
    }
}
