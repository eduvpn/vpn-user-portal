<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use DateTime;
use fkooman\OAuth\Server\StorageInterface;
use fkooman\SqliteMigrate\Migration;
use LetsConnect\Common\Http\CredentialValidatorInterface;
use LetsConnect\Common\Http\UserInfo;
use PDO;
use PDOException;

class Storage implements CredentialValidatorInterface, StorageInterface
{
    const CURRENT_SCHEMA_VERSION = '0000000000';

    /** @var \PDO */
    private $db;

    /** @var \DateTime */
    private $dateTime;

    /** @var \fkooman\SqliteMigrate\Migration */
    private $migration;

    /**
     * @param \PDO      $db
     * @param string    $schemaDir
     * @param \DateTime $dateTime
     */
    public function __construct(PDO $db, $schemaDir, DateTime $dateTime)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ('sqlite' === $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $db->exec('PRAGMA foreign_keys = ON');
        }
        $this->db = $db;
        $this->migration = new Migration($db, $schemaDir, self::CURRENT_SCHEMA_VERSION);
        $this->dateTime = $dateTime;
    }

    /**
     * @param string $authUser
     * @param string $authPass
     *
     * @return false|UserInfo
     */
    public function isValid($authUser, $authPass)
    {
        $stmt = $this->db->prepare(
            'SELECT
                password_hash
             FROM users
             WHERE
                user_id = :user_id'
        );

        $stmt->bindValue(':user_id', $authUser, PDO::PARAM_STR);
        $stmt->execute();
        $dbHash = $stmt->fetchColumn(0);
        $isVerified = password_verify($authPass, $dbHash);
        if ($isVerified) {
            return new UserInfo($authUser, [], new DateTime());
        }

        return false;
    }

    /**
     * @param string $userId
     * @param string $userPass
     *
     * @return void
     */
    public function add($userId, $userPass)
    {
        if ($this->userExists($userId)) {
            $this->updatePassword($userId, $userPass);

            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO
                users (user_id, password_hash, created_at)
            VALUES
                (:user_id, :password_hash, :created_at)'
        );

        $passwordHash = password_hash($userPass, PASSWORD_DEFAULT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
        $stmt->bindValue(':created_at', $this->dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $authUser
     *
     * @return bool
     */
    public function userExists($authUser)
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*)
             FROM users
             WHERE
                user_id = :user_id'
        );

        $stmt->bindValue(':user_id', $authUser, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === (int) $stmt->fetchColumn();
    }

    /**
     * @param string $userId
     * @param string $newUserPass
     *
     * @return bool
     */
    public function updatePassword($userId, $newUserPass)
    {
        $stmt = $this->db->prepare(
            'UPDATE
                users
             SET
                password_hash = :password_hash
             WHERE
                user_id = :user_id'
        );

        $passwordHash = password_hash($newUserPass, PASSWORD_DEFAULT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    /**
     * @param string $authKey
     *
     * @return bool
     */
    public function logAuthKey($authKey)
    {
        // we will attempt to store the authKey in the database, there is a
        // duplicate contraint, so it will fail if the identical authKey is
        // already there
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO auth_key_log (
                    auth_key
                 ) 
                 VALUES(
                    :auth_key
                 )'
            );

            $stmt->bindValue(':auth_key', $authKey, PDO::PARAM_STR);
            $stmt->execute();

            return true;
        } catch (PDOException $e) {
            // insert failed, so this is a replay of the authorization code
            return false;
        }
    }

    /**
     * @param string $authKey
     *
     * @return bool
     */
    public function hasAuthorization($authKey)
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*)
             FROM authorizations
             WHERE
                auth_key = :auth_key'
        );

        $stmt->bindValue(':auth_key', $authKey, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === (int) $stmt->fetchColumn(0);
    }

    /**
     * @param string $userId
     * @param string $clientId
     * @param string $scope
     * @param string $authKey
     *
     * @return void
     */
    public function storeAuthorization($userId, $clientId, $scope, $authKey)
    {
        $stmt = $this->db->prepare(
            'INSERT INTO authorizations (
                auth_key,
                user_id,
                client_id,
                scope
             ) 
             VALUES(
                :auth_key,
                :user_id, 
                :client_id,
                :scope
             )'
        );

        $stmt->bindValue(':auth_key', $authKey, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':scope', $scope, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return array
     */
    public function getAuthorizations($userId)
    {
        $stmt = $this->db->prepare(
            'SELECT
                auth_key,
                client_id,
                scope
             FROM authorizations
             WHERE
                user_id = :user_id
             GROUP BY client_id, scope'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $authKey
     *
     * @return void
     */
    public function deleteAuthKey($authKey)
    {
        $stmt = $this->db->prepare(
            'DELETE FROM
                authorizations
             WHERE
                auth_key = :auth_key'
        );

        $stmt->bindValue(':auth_key', $authKey, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $userId
     * @param string $clientId
     * @param string $scope
     *
     * @return void
     */
    public function deleteAuthorization($userId, $clientId, $scope)
    {
        $stmt = $this->db->prepare(
            'DELETE FROM
                authorizations
             WHERE
                user_id = :user_id
             AND
                client_id = :client_id
             AND
                scope = :scope'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':scope', $scope, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @return void
     */
    public function init()
    {
        $this->migration->init();
    }

    /**
     * @return void
     */
    public function update()
    {
        $this->migration->run();
    }
}
