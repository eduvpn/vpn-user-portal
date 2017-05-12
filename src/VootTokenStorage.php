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
