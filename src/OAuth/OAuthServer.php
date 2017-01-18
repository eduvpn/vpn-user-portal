<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SURFnet\VPN\Portal\OAuth;

use DateInterval;
use DateTime;
use SURFnet\VPN\Common\Http\Exception\HttpException;
use SURFnet\VPN\Common\RandomInterface;

class OAuthServer
{
    /** @var TokenStorage */
    private $tokenStorage;

    /** @var \SURFnet\VPN\Common\RandomInterface */
    private $random;

    /** @var \DateTime */
    private $dateTime;

    /** @var callable */
    private $getClientInfo;

    public function __construct(TokenStorage $tokenStorage, RandomInterface $random, DateTime $dateTime, callable $getClientInfo)
    {
        $this->tokenStorage = $tokenStorage;
        $this->random = $random;
        $this->dateTime = $dateTime;
        $this->getClientInfo = $getClientInfo;
    }

    /**
     * Validates the request from the client and returns verified data to
     * show an authorization dialog.
     *
     * @return array
     */
    public function getAuthorize(array $getData, $userId)
    {
        $this->validateQueryParameters($getData);
        $clientInfo = $this->validateClient($getData['client_id'], $getData['response_type'], $getData['redirect_uri']);

        return [
            'client_id' => $getData['client_id'],
            'display_name' => $clientInfo['display_name'],
            'scope' => $getData['scope'],
            'redirect_uri' => $getData['redirect_uri'],
        ];
    }

    /**
     * @return string the redirect_uri
     */
    public function postAuthorize(array $getData, array $postData, $userId)
    {
        // XXX the Referer MUST be equal to the current URL
        $this->validateQueryParameters($getData);
        $this->validateClient($getData['client_id'], $getData['response_type'], $getData['redirect_uri']);
        $this->validatePostParameters($postData);

        switch ($getData['response_type']) {
            case 'token':
                return $this->tokenAuthorize($getData, $postData, $userId);
            case 'code':
                return $this->codeAuthorize($getData, $postData, $userId);
            default:
                throw new HttpException('invalid "response_type"', 400);
        }
    }

    public function postToken(array $postData)
    {
        // for now only "public" clients without authentication
        $this->validateTokenPostParameters($postData);
        $this->validateClient($postData['client_id'], 'code', $postData['redirect_uri']);

        list($authorizationCodeKey, $authorizationCode) = explode('.', $postData['authorization_code']);
        $codeInfo = $this->tokenStorage->getCode($authorizationCodeKey);

        if (!hash_equals($codeInfo['authorization_code'], $authorizationCode)) {
            throw new HttpException('invalid "authorization_code"', 400);
        }

        // XXX make sure this is correct!
        if ($this->dateTime->sub(new DateInterval('-10 minutes')) < new DateTime($codeInfo['issued_at'])) {
            throw new HttpException('expired "authorization_code"', 400);
        }

        if ($postData['redirect_uri'] !== $codeInfo['redirect_uri']) {
            throw new HttpException('unexpected "redirect_uri"', 400);
        }

        if ($postData['client_id'] !== $codeInfo['client_id']) {
            throw new HttpException('unexpected "client_id"', 400);
        }

        // XXX we should link the code to the access token to be able to revoke it?
        $accessToken = $this->getAccessToken(
            $codeInfo['user_id'],
            $postData['client_id'],
            $postData['scope']
        );

        return [
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            //'scope' => 'XXX'  // only if it changed...
        ];
    }

    private function tokenAuthorize(array $getData, array $postData, $userId)
    {
        if ('no' === $postData['approve']) {
            return $this->prepareRedirect(
                '#',
                $getData['redirect_uri'],
                [
                    'error' => 'access_denied',
                    'error_description' => 'user refused authorization',
                    'state' => $getData['state'],
                ]
            );
        }

        $accessToken = $this->getAccessToken(
            $userId,
            $getData['client_id'],
            $getData['scope']
        );

        return $this->prepareRedirect(
            '#',
            $getData['redirect_uri'],
            [
                'access_token' => $accessToken,
                'state' => $getData['state'],
            ]
        );
    }

    private function codeAuthorize(array $getData, array $postData, $userId)
    {
        if ('no' === $postData['approve']) {
            return $this->prepareRedirect(
                '?',
                $getData['redirect_uri'],
                [
                    'error' => 'access_denied',
                    'error_description' => 'user refused authorization',
                    'state' => $getData['state'],
                ]
            );
        }

        $authorizationCode = $this->getAuthorizationCode(
            $userId,
            $getData['client_id'],
            $getData['scope'],
            $getData['redirect_uri']
        );

        return $this->prepareRedirect(
            '?',
            $getData['redirect_uri'],
            [
                'authorization_code' => $authorizationCode,
                'state' => $getData['state'],
            ]
        );
    }

    private function prepareRedirect($querySeparator, $redirectUri, array $queryParameters)
    {
        return sprintf(
            '%s%s%s',
            $redirectUri,
            $querySeparator,
            http_build_query($queryParameters)
        );
    }

    private function getAccessToken($userId, $clientId, $scope)
    {
        $existingToken = $this->tokenStorage->getExistingToken(
            $userId,
            $clientId,
            $scope
        );

        if (false !== $existingToken) {
            // if the user already has an access_token for this client and
            // scope, reuse it
            $accessTokenKey = $existingToken['access_token_key'];
            $accessToken = $existingToken['access_token'];
        } else {
            // generate a new one
            $accessTokenKey = $this->random->get(8);
            $accessToken = $this->random->get(16);
            // store it
            $this->tokenStorage->storeToken(
                $userId,
                $accessTokenKey,
                $accessToken,
                $clientId,
                $scope
            );
        }

        return sprintf('%s.%s', $accessTokenKey, $accessToken);
    }

    private function getAuthorizationCode($userId, $clientId, $scope, $redirectUri)
    {
        $authorizationCodeKey = $this->random->get(8);
        $authorizationCode = $this->random->get(16);

        $this->tokenStorage->storeCode(
            $userId,
            $authorizationCodeKey,
            $authorizationCode,
            $clientId,
            $scope,
            $redirectUri,
            $this->dateTime
        );

        return sprintf('%s.%s', $authorizationCodeKey, $authorizationCode);
    }

    private function validateQueryParameters(array $getData)
    {
        // check all parameters are there
        foreach (['client_id', 'redirect_uri', 'response_type', 'scope', 'state'] as $queryParameter) {
            if (!array_key_exists($queryParameter, $getData)) {
                throw new HttpException(sprintf('missing "%s" parameter', $queryParameter), 400);
            }
        }

        // check syntax
        $this->validateClientId($getData['client_id']);
        $this->validateRedirectUri($getData['redirect_uri']);
        $this->validateResponseType($getData['response_type']);
        $this->validateScope($getData['scope']);
        $this->validateState($getData['state']);
    }

    private function validateTokenPostParameters(array $postData)
    {
        // check all parameters are there
        foreach (['grant_type', 'code', 'redirect_uri', 'client_id'] as $postParameter) {
            if (!array_key_exists($postParameter, $postData)) {
                throw new HttpException(sprintf('missing "%s" parameter', $postParameter), 400);
            }
        }

        // check syntax
        $this->validateGrantType($postData['grant_type']);
        $this->validateCode($postData['code']);
        $this->validateRedirectUri($postData['redirect_uri']);
        $this->validateClientId($postData['client_id']);
    }

    private function validatePostParameters(array $postData)
    {
        // check all parameters are there
        foreach (['approve'] as $postParameter) {
            if (!array_key_exists($postParameter, $postData)) {
                throw new HttpException(sprintf('missing "%s" parameter', $postParameter), 400);
            }
        }

        $this->validateApprove($postData['approve']);
    }

    private function validateClient($clientId, $responseType, $redirectUri)
    {
        $clientInfo = call_user_func($this->getClientInfo, $clientId);
        if (false === $clientInfo) {
            throw new HttpException(sprintf('client "%s" not registered', $clientId), 400);
        }

        if ($clientInfo['response_type'] !== $responseType) {
            throw new HttpException('invalid response_type for this client_id', 400);
        }

        if ($clientInfo['redirect_uri'] !== $redirectUri) {
            throw new HttpException(sprintf('"redirect_uri" does not match expected value "%s"', $clientInfo['redirect_uri']), 400);
        }

        return $clientInfo;
    }

    // STRING VALIDATORS

    private function validateClientId($clientId)
    {
        if (1 !== preg_match('/^(?:[\x20-\x7E])+$/', $clientId)) {
            throw new HttpException('invalid "client_id"', 400);
        }
    }

    private function validateRedirectUri($redirectUri)
    {
        if (false === filter_var($redirectUri, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED | FILTER_FLAG_PATH_REQUIRED)) {
            throw new HttpException('invalid "redirect_uri"', 400);
        }
        if (false !== strpos($redirectUri, '?')) {
            throw new HttpException('"redirect_uri" cannot contain "?"', 400);
        }
    }

    private function validateCode($code)
    {
        if (1 !== preg_match('/^(?:[\x20-\x7E])+$/', $code)) {
            throw new HttpException('invalid "code"', 400);
        }
    }

    private function validateGrantType($grantType)
    {
        if ('authorization_code' !== $grantType) {
            throw new HttpException('invalid "grant_type"', 400);
        }
    }

    private function validateResponseType($responseType)
    {
        if (!in_array($responseType, ['token', 'code'])) {
            throw new HttpException('invalid "response_type"', 400);
        }
    }

    private function validateScope($scope)
    {
        // XXX allow more values here, maybe statically defined at top of class
        if ('config' !== $scope) {
            throw new HttpException('invalid "scope"', 400);
        }
    }

    private function validateState($state)
    {
        if (1 !== preg_match('/^(?:[\x20-\x7E])+$/', $state)) {
            throw new HttpException('invalid "state"', 400);
        }
    }

    private function validateApprove($approve)
    {
        // check they are all syntactically correct
        if (!in_array($approve, ['yes', 'no'])) {
            throw new HttpException('invalid "approve"', 400);
        }
    }
}
