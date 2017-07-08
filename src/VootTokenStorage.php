<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Client\AccessToken;
use fkooman\OAuth\Client\TokenStorageInterface;
use RuntimeException;
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
     * @return array
     */
    public function getAccessTokenList($userId)
    {
        // vpn-user-portal will never use this
        throw new RuntimeException('not implemented');
    }

    /**
     * @param string      $userId
     * @param AccessToken $accessToken
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
     */
    public function deleteAccessToken($userId, AccessToken $accessToken)
    {
        // vpn-user-portal will never use this
        throw new RuntimeException('not implemented');
    }
}
