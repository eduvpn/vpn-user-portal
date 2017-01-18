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

use SURFnet\VPN\Common\Http\Exception\HttpException;
use SURFnet\VPN\Common\RandomInterface;

class OAuthServer
{
    /** @var TokenStorage */
    private $tokenStorage;

    /** @var \SURFnet\VPN\Common\RandomInterface */
    private $random;

    /** @var callable */
    private $getClientInfo;

    public function __construct(TokenStorage $tokenStorage, RandomInterface $random, callable $getClientInfo)
    {
        $this->tokenStorage = $tokenStorage;
        $this->random = $random;
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
        $clientInfo = $this->validateClient($getData);

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
        $this->validateClient($getData);
        $this->validatePostParameters($postData);

        $returnUriPattern = '%s#%s';

        if ('no' === $postData['approve']) {
            $redirectQuery = http_build_query(
                [
                    'error' => 'access_denied',
                    'error_description' => 'user refused authorization',
                    'state' => $getData['state'],
                ]
            );

            return sprintf($returnUriPattern, $getData['redirect_uri'], $redirectQuery);
        }

        $accessToken = $this->getAccessToken(
            $userId,
            $getData['client_id'],
            $getData['scope']
        );

        // add state, access_token to redirect_uri
        $redirectQuery = http_build_query(
            [
                'access_token' => $accessToken,
                'state' => $getData['state'],
            ]
        );

        return sprintf($returnUriPattern, $getData['redirect_uri'], $redirectQuery);
    }

    public function postToken(array $postData)
    {
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
            $this->tokenStorage->store(
                $userId,
                $accessTokenKey,
                $accessToken,
                $clientId,
                $scope
            );
        }

        return sprintf('%s.%s', $accessTokenKey, $accessToken);
    }

    private function validateQueryParameters(array $getData)
    {
        // check all parameters are there
        foreach (['client_id', 'redirect_uri', 'response_type', 'scope', 'state'] as $queryParameter) {
            if (!array_key_exists($queryParameter, $getData)) {
                throw new HttpException(sprintf('missing "%s" parameter', $queryParameter), 400);
            }
        }

        // check they are all syntactically correct
        if (1 !== preg_match('/^(?:[\x20-\x7E])+$/', $getData['client_id'])) {
            throw new HttpException('invalid client_id', 400);
        }

        // XXX cannot contain '?'
        if (false === filter_var($getData['redirect_uri'], FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED | FILTER_FLAG_PATH_REQUIRED)) {
            throw new HttpException('invalid "redirect_uri"', 400);
        }

        if (!in_array($getData['response_type'], ['token', 'code'])) {
            throw new HttpException('invalid "response_type"', 400);
        }

        // XXX allow more values here
        if ('config' !== $getData['scope']) {
            throw new HttpException('invalid "scope"', 400);
        }

        if (1 !== preg_match('/^(?:[\x20-\x7E])+$/', $getData['state'])) {
            throw new HttpException('invalid state', 400);
        }
    }

    private function validatePostParameters(array $postData)
    {
        // check all parameters are there
        foreach (['approve'] as $postParameter) {
            if (!array_key_exists($postParameter, $postData)) {
                throw new HttpException(sprintf('missing "%s" parameter', $postParameter), 400);
            }
        }

        // check they are all syntactically correct
        if (!in_array($postData['approve'], ['yes', 'no'])) {
            throw new HttpException('invalid "approve"', 400);
        }
    }

    private function validateClient(array $getData)
    {
        $clientInfo = call_user_func($this->getClientInfo, $getData['client_id']);
        if (false === $clientInfo) {
            throw new HttpException(sprintf('client "%s" not registered', $getData['client_id']), 400);
        }

        if ($clientInfo['response_type'] !== $getData['response_type']) {
            throw new HttpException('invalid response_type for this client_id', 400);
        }

        if ($clientInfo['redirect_uri'] !== $getData['redirect_uri']) {
            throw new HttpException(sprintf('"redirect_uri" does not match expected value "%s"', $clientInfo['redirect_uri']), 400);
        }

        return $clientInfo;
    }
}
