<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Closure;
use DateTimeImmutable;
use fkooman\OAuth\Server\Exception\OAuthException;
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use fkooman\OAuth\Server\ValidatorInterface;
use Vpn\Portal\Dt;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\ServerList;
use Vpn\Portal\Storage;

class GuestApiService implements ApiServiceInterface
{
    /** @var array<string,array<string,Closure(Request):Response>> */
    protected array $beforeAuthRouteList = [];

    /** @var array<string,array<string,Closure(Request,ApiUserInfo):Response>> */
    protected array $routeList = [];

    private ValidatorInterface $bearerValidator;
    private ServerList $serverList;
    private Storage $storage;
    private OAuthStorage $oauthStorage;
    private string $localKeyId;
    private DateTimeImmutable $dateTime;

    public function __construct(ValidatorInterface $bearerValidator, ServerList $serverList, Storage $storage, OAuthStorage $oauthStorage, string $localKeyId)
    {
        $this->bearerValidator = $bearerValidator;
        $this->serverList = $serverList;
        $this->storage = $storage;
        $this->oauthStorage = $oauthStorage;
        $this->localKeyId = $localKeyId;
        $this->dateTime = Dt::get();
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

            $userId = $accessToken->userId();

            if (null === $rawAccessToken = $accessToken->raw()) {
                throw new HttpException('unable to get "raw" access token', 500);
            }
            [,$keyId] = explode('.', $rawAccessToken, 4);

            if ($this->localKeyId !== $keyId) {
                // we have a "Guest" user
                if (null === $baseUrl = $this->serverList->extractBaseUrl($keyId)) {
                    throw new HttpException('unable to extract "baseUrl" using "keyId"', 500);
                }
                $domainName = parse_url($baseUrl, PHP_URL_HOST);
                if (!\is_string($domainName)) {
                    throw new HttpException('unable to extract domain name from "baseUrl"', 500);
                }

                $userId = sprintf('%s@%s', $userId, $domainName);
            }

            // technically we only should do the following for "Guest" users,
            // as for local users this should all be there already...
            // this again asks for a solution where we move this to the
            // Bearer validator, i.e. the AccessTokenVerifierInterface
            // implementation for "Guest Usage", but that would require
            // fkooman/oauth2-server 8.x in order to break API... not
            // necessarily a problem, let's tackle that next, one thing at a
            // time...

            // make sure the user exists
            if (!$this->storage->userExists($userId)) {
                $this->storage->userAdd(new UserInfo($userId, []), $this->dateTime);
            }

            // make sure the user account is NOT disabled
            if ($this->storage->userIsDisabled($userId)) {
                throw new HttpException('account disabled', 403);
            }

            // make sure the authorization exists locally
            if (null === $this->oauthStorage->getAuthorization($accessToken->authKey())) {
                $this->oauthStorage->storeAuthorization(
                    $userId,
                    $accessToken->clientId(),
                    $accessToken->scope(),
                    $accessToken->authKey(),
                    $this->dateTime,
                    $accessToken->authorizationExpiresAt()
                );
            }

            if (!\array_key_exists($pathInfo, $this->routeList)) {
                throw new HttpException(sprintf('"%s" not found', $pathInfo), 404);
            }
            if (!\array_key_exists($requestMethod, $this->routeList[$pathInfo])) {
                throw new HttpException(sprintf('method "%s" not allowed', $requestMethod), 405, ['Allow' => implode(',', array_keys($this->routeList[$pathInfo]))]);
            }

            return $this->routeList[$pathInfo][$requestMethod]($request, new ApiUserInfo($userId, [], $accessToken));
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
