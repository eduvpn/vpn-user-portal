<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\Exception\OAuthException;
use LC\Portal\Http\Exception\HttpException;

class ApiService implements ServiceInterface
{
    private BearerValidator $bearerValidator;
    private array $routeList = [];

    public function __construct(BearerValidator $bearerValidator)
    {
        $this->bearerValidator = $bearerValidator;
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
        // NOP
    }

    public function addModule(ServiceModuleInterface $module): void
    {
        $module->init($this);
    }

    public function run(Request $request): Response
    {
        try {
            $accessToken = $this->bearerValidator->validate();
            $accessToken->scope()->requireAll(['config']);

            $pathInfo = $request->getPathInfo();
            $requestMethod = $request->getRequestMethod();

            if (!\array_key_exists($pathInfo, $this->routeList)) {
                return new JsonResponse(['error' => sprintf('"%s" not found', $pathInfo)], [], 404);
            }
            if (!\array_key_exists($requestMethod, $this->routeList[$pathInfo])) {
                return new JsonResponse(['error' => sprintf('method "%s" not allowed', $requestMethod)], ['Allow' => implode(',', array_keys($this->routeList[$pathInfo]))], 405);
            }

            return $this->routeList[$pathInfo][$requestMethod]($accessToken, $request);
        } catch (OAuthException $e) {
            $jsonResponse = $e->getJsonResponse();

            // XXX convert to JsonResponse?
            return new Response($jsonResponse->getBody(), $jsonResponse->getHeaders(), $jsonResponse->getStatusCode());
        } catch (HttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->responseHeaders(), $e->statusCode());
        }
    }
}
