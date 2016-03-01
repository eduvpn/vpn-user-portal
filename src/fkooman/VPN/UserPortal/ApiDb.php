<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\VPN\UserPortal;

use PDO;
use RuntimeException;

class ApiDb
{
    /** @var PDO */
    private $db;

    /** @var string */
    private $prefix;

    public function __construct(PDO $db, $prefix = '')
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
        $this->prefix = $prefix;
    }

    public function addKey($userId, $userName, $userPassHash)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (
                    user_id,
                    user_name,
                    user_pass_hash
                 ) 
                 VALUES(
                    :user_id, 
                    :user_name, 
                    :user_pass_hash
                 )',
                $this->prefix.'api'
            )
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':user_name', $userName, PDO::PARAM_STR);
        $stmt->bindValue(':user_pass_hash', $userPassHash, PDO::PARAM_STR);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to insert');
        }
    }

    public function deleteKey($userId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'DELETE FROM %s
                 WHERE
                    user_id = :user_id',
                $this->prefix.'api'
            )
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getUserNameForUserId($userId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT user_name FROM %s
                 WHERE
                    user_id = :user_id',
                $this->prefix.'api'
            )
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserIdForUserName($userName)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT user_id FROM %s
                 WHERE
                    user_name = :user_name',
                $this->prefix.'api'
            )
        );

        $stmt->bindValue(':user_name', $userName, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getHashForUserName($userName)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT user_pass_hash FROM %s
                 WHERE
                    user_name = :user_name',
                $this->prefix.'api'
            )
        );

        $stmt->bindValue(':user_name', $userName, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function createTableQueries($prefix)
    {
        $query = array(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    user_id VARCHAR(255) NOT NULL,
                    user_name VARCHAR(255) NOT NULL,
                    user_pass_hash VARCHAR(255) NOT NULL,
                    UNIQUE(user_id),
                    UNIQUE(user_name)
                )',
                $prefix.'api'
            ),
        );

        return $query;
    }

    public function initDatabase()
    {
        $queries = self::createTableQueries($this->prefix);
        foreach ($queries as $q) {
            $this->db->query($q);
        }

        $tables = array('api');
        foreach ($tables as $t) {
            // make sure the tables are empty
            $this->db->query(
                sprintf(
                    'DELETE FROM %s',
                    $this->prefix.$t
                )
            );
        }
    }
}
