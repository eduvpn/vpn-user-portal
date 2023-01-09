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
use fkooman\OAuth\Server\Exception\OAuthException;
use fkooman\OAuth\Server\ValidatorInterface;
use Vpn\Portal\Http\Exception\HttpException;

class ApiService implements ApiServiceInterface
{
    /** @var array<string,array<string,Closure(Request):Response>> */
    protected array $beforeAuthRouteList = [];

    /** @var array<string,array<string,Closure(Request,ApiUserInfo):Response>> */
    protected array $routeList = [];

    private ValidatorInterface $bearerValidator;

    public function __construct(ValidatorInterface $bearerValidator)
    {
        $this->bearerValidator = $bearerValidator;
    }

    /**
     * @param Closure(Request,ApiUserInfo):Response $closure
     */
    public function get(string $pathInfo, Closure $closure): void
    {
        $this->routeList[$pathInfo]['GET'] = $closure;
    }

    /**
     * @param Closure(Request,ApiUserInfo):Response $closure
     */
    public function post(string $pathInfo, Closure $closure): void
    {
        $this->routeList[$pathInfo]['POST'] = $closure;
    }

    public function addModule(ApiServiceModuleInterface $module): void
    {
        $module->init($this);
    }

    public function run(Request $request): Response
    {
        $requestMethod = $request->getRequestMethod();
        $pathInfo = $request->getPathInfo();

        try {
            $accessToken = $this->bearerValidator->validate();
            $accessToken->scope()->requireAll(['config']);

            if (!\array_key_exists($pathInfo, $this->routeList)) {
                throw new HttpException(sprintf('"%s" not found', $pathInfo), 404);
            }
            if (!\array_key_exists($requestMethod, $this->routeList[$pathInfo])) {
                throw new HttpException(sprintf('method "%s" not allowed', $requestMethod), 405, ['Allow' => implode(',', array_keys($this->routeList[$pathInfo]))]);
            }

            return $this->routeList[$pathInfo][$requestMethod]($request, new ApiUserInfo($accessToken->userId(), [], $accessToken));
        } catch (OAuthException $e) {
            return new Response(
                $e->getJsonResponse()->getBody(),
                $e->getJsonResponse()->getHeaders(),
                $e->getJsonResponse()->getStatusCode()
            );
        } catch (HttpException $e) {
            return new JsonResponse(
                [
                    'error' => $e->getMessage(),
                ],
                $e->responseHeaders(),
                $e->statusCode()
            );
        }
    }
}
