<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Client\AccessToken;
use fkooman\OAuth\Client\TokenStorageInterface;
use SURFnet\VPN\Common\HttpClient\ServerClient;

class VootTokenStorage implements TokenStorageInterface
{
    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    public function __construct(ServerClient $serverClient)
    {
        $this->serverClient = $serverClient;
    }

    /**
     * @param string $userId
     *
     * @return array<AccessToken>
     */
    public function getAccessTokenList($userId)
    {
        // figure out if we have a VOOT token
        $hasVootToken = $this->serverClient->getRequireBool(
            'has_voot_token',
            [
                'user_id' => $userId,
            ]
        );
        if (false === $hasVootToken) {
            // no VOOT token, return empty list
            return [];
        }

        // yes VOOT token
        $vootToken = $this->serverClient->getRequireString(
            'get_voot_token',
            [
                'user_id' => $userId,
            ]
        );

        return [
            AccessToken::fromJson($vootToken),
        ];
    }

    /**
     * @param string      $userId
     * @param AccessToken $accessToken
     *
     * @return void
     */
    public function storeAccessToken($userId, AccessToken $accessToken)
    {
        $this->serverClient->post(
            'set_voot_token',
            [
                'user_id' => $userId,
                'voot_token' => $accessToken->toJson(),
            ]
        );
    }

    /**
     * @param string      $userId
     * @param AccessToken $accessToken
     *
     * @return void
     */
    public function deleteAccessToken($userId, AccessToken $accessToken)
    {
        $this->serverClient->post(
            'delete_voot_token',
            [
                'user_id' => $userId,
            ]
        );
    }
}
