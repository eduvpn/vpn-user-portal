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

use PDO;
use PDOException;

class TokenStorage
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
    }

    public function store($userId, $accessTokenKey, $accessToken, $clientId, $scope)
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tokens (
                user_id,    
                access_token_key,
                access_token,
                client_id,
                scope
             ) 
             VALUES(
                :user_id, 
                :access_token_key,
                :access_token,
                :client_id,
                :scope
             )'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':access_token_key', $accessTokenKey, PDO::PARAM_STR);
        $stmt->bindValue(':access_token', $accessToken, PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':scope', $scope, PDO::PARAM_STR);
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }

        return true;
    }

    public function getExistingToken($userId, $clientId, $scope)
    {
        $stmt = $this->db->prepare(
            'SELECT
                access_token_key,
                access_token
             FROM tokens
             WHERE
                user_id = :user_id AND
                client_id = :client_id AND
                scope = :scope'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':scope', $scope, PDO::PARAM_STR);

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function get($accessTokenKey)
    {
        $stmt = $this->db->prepare(
            'SELECT
                user_id,    
                access_token,
                client_id,
                scope
             FROM tokens
             WHERE
                access_token_key = :access_token_key'
        );

        $stmt->bindValue(':access_token_key', $accessTokenKey, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAuthorizedClients($userId)
    {
        $stmt = $this->db->prepare(
            'SELECT
                client_id,
                scope
             FROM tokens
             WHERE
                user_id = :user_id'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function removeClientTokens($userId, $clientId)
    {
        $stmt = $this->db->prepare(
            'DELETE FROM tokens
             WHERE user_id = :user_id AND client_id = :client_id'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }

        return true;
    }

    public function init()
    {
        $queryList = [
            'CREATE TABLE IF NOT EXISTS tokens (
                user_id VARCHAR(255) NOT NULL,
                access_token_key VARCHAR(255) NOT NULL,
                access_token VARCHAR(255) NOT NULL,
                client_id VARCHAR(255) NOT NULL,
                scope VARCHAR(255) NOT NULL,
                UNIQUE(access_token_key)
            )',
        ];

        foreach ($queryList as $query) {
            $this->db->query($query);
        }
    }
}
