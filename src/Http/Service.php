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
use LC\Portal\Json;
use LC\Portal\TplInterface;

class Service
{
    /** @var \LC\Portal\TplInterface|null */
    private $tpl;

    /** @var array */
    private $routes;

    /** @var array */
    private $beforeHooks;

    /** @var array */
    private $afterHooks;

    public function __construct(TplInterface $tpl = null)
    {
        $this->tpl = $tpl;
        $this->routes = [
            'GET' => [],
            'POST' => [],
        ];
        $this->beforeHooks = [];
        $this->afterHooks = [];
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function addBeforeHook($name, BeforeHookInterface $beforeHook)
    {
        $this->beforeHooks[$name] = $beforeHook;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function addAfterHook($name, AfterHookInterface $afterHook)
    {
        $this->afterHooks[$name] = $afterHook;
    }

    /**
     * @param string $requestMethod
     * @param string $pathInfo
     *
     * @return void
     */
    public function addRoute($requestMethod, $pathInfo, callable $callback)
    {
        $this->routes[$requestMethod][$pathInfo] = $callback;
    }

    /**
     * @param string $pathInfo
     *
     * @return void
     */
    public function get($pathInfo, callable $callback)
    {
        $this->addRoute('GET', $pathInfo, $callback);
    }

    /**
     * @param string $pathInfo
     *
     * @return void
     */
    public function post($pathInfo, callable $callback)
    {
        $this->addRoute('POST', $pathInfo, $callback);
    }

    /**
     * @return void
     */
    public function addModule(ServiceModuleInterface $module)
    {
        $module->init($this);
    }

    /**
     * @return Response
     */
    public function run(Request $request)
    {
        try {
            // before hooks
            $hookData = [];
            foreach ($this->beforeHooks as $k => $v) {
                $hookResponse = $v->executeBefore($request, $hookData);
                // if we get back a Response object, return it immediately
                if ($hookResponse instanceof Response) {
                    // run afterHooks
                    return $this->runAfterHooks($request, $hookResponse);
                }

                $hookData[$k] = $hookResponse;
            }

            $requestMethod = $request->getRequestMethod();
            // if we get a HEAD request, that is just the same for us as a GET
            // request, the web server will strip the data...
            if ('HEAD' === $requestMethod) {
                $requestMethod = 'GET';
            }

            $pathInfo = $request->getPathInfo();

            if (!\array_key_exists($requestMethod, $this->routes)) {
                throw new HttpException(
                    sprintf('method "%s" not allowed', $requestMethod),
                    405,
                    ['Allow' => implode(',', array_keys($this->routes))]
                );
            }
            if (!\array_key_exists($pathInfo, $this->routes[$requestMethod])) {
                throw new HttpException(
                    sprintf('"%s" not found', $pathInfo),
                    404
                );
            }

            $response = $this->routes[$requestMethod][$pathInfo]($request, $hookData);

            // after hooks
            return $this->runAfterHooks($request, $response);
        } catch (HttpException $e) {
            if ($request->isBrowser()) {
                if (null === $this->tpl) {
                    // template not available
                    $response = new Response((int) $e->getCode(), 'text/plain');
                    $response->setBody(sprintf('%d: %s', (int) $e->getCode(), $e->getMessage()));
                } else {
                    // template available
                    $response = new Response((int) $e->getCode(), 'text/html');
                    $response->setBody(
                        $this->tpl->render(
                            'errorPage',
                            [
                                'e' => $e,
                            ]
                        )
                    );
                }
            } else {
                // not a browser
                $response = new Response((int) $e->getCode(), 'application/json');
                $response->setBody(Json::encode(['error' => $e->getMessage()]));
            }

            foreach ($e->getResponseHeaders() as $key => $value) {
                $response->addHeader($key, $value);
            }

            // after hooks
            return $this->runAfterHooks($request, $response);
        }
    }

    /**
     * @param Request                     $request
     * @param array<string,array<string>> $whiteList
     *
     * @return bool
     */
    public static function isWhitelisted(Request $request, array $whiteList)
    {
        if (!\array_key_exists($request->getRequestMethod(), $whiteList)) {
            return false;
        }

        return \in_array($request->getPathInfo(), $whiteList[$request->getRequestMethod()], true);
    }

    /**
     * @return Response
     */
    private function runAfterHooks(Request $request, Response $response)
    {
        foreach ($this->afterHooks as $v) {
            $response = $v->executeAfter($request, $response);
        }

        return $response;
    }
}
