<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateTime;
use fkooman\OAuth\Server\Authorization;
use fkooman\OAuth\Server\StorageInterface;
use LC\Common\Json;
use PDO;

class Storage implements StorageInterface
{
    const CURRENT_SCHEMA_VERSION = '2021032401';

    private PDO $db;

    private Migration $migration;

    public function __construct(PDO $db, string $schemaDir)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ('sqlite' === $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $db->exec('PRAGMA foreign_keys = ON');
        }
        $this->db = $db;
        $this->migration = new Migration($db, $schemaDir, self::CURRENT_SCHEMA_VERSION);
    }

    public function getPasswordHash(string $authUser): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT
                password_hash
             FROM local_users
             WHERE
                user_id = :user_id'
        );

        $stmt->bindValue(':user_id', $authUser, PDO::PARAM_STR);
        $stmt->execute();
        $resultColumn = $stmt->fetchColumn(0);

        return \is_string($resultColumn) ? $resultColumn : null;
    }

    public function wgAddPeer(string $userId, string $profileId, string $displayName, string $publicKey, string $ipFour, string $ipSix, DateTime $createdAt, ?string $clientId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO wg_peers (
                user_id,
                profile_id,
                display_name,
                public_key,
                ip_four,
                ip_six,
                created_at,
                client_id
             )
             VALUES(
                :user_id,
                :profile_id,
                :display_name,
                :public_key,
                :ip_four,
                :ip_six,
                :created_at,
                :client_id
             )'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':display_name', $displayName, PDO::PARAM_STR);
        $stmt->bindValue(':public_key', $publicKey, PDO::PARAM_STR);
        $stmt->bindValue(':ip_four', $ipFour, PDO::PARAM_STR);
        $stmt->bindValue(':ip_six', $ipSix, PDO::PARAM_STR);
        $stmt->bindValue(':created_at', $createdAt->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR | PDO::PARAM_NULL);
        $stmt->execute();
    }

    public function wgRemovePeer(string $userId, string $publicKey): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM
                wg_peers
             WHERE
                user_id = :user_id
             AND
                public_key = :public_key'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':public_key', $publicKey, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @return array<string>
     */
    public function wgGetAllocatedIpFourAddresses(): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                ip_four
             FROM wg_peers'
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return array<array{display_name:string,public_key:string,ip_four:string,ip_six:string,created_at:\DateTime,client_id:string|null}>
     */
    public function wgGetPeers(string $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                profile_id,
                display_name,
                public_key,
                ip_four,
                ip_six,
                created_at,
                client_id
             FROM wg_peers
             WHERE
                user_id = :user_id'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        $wgPeers = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $wgPeers[] = [
                'profile_id' => (string) $resultRow['profile_id'],
                'display_name' => (string) $resultRow['display_name'],
                'public_key' => (string) $resultRow['public_key'],
                'ip_four' => (string) $resultRow['ip_four'],
                'ip_six' => (string) $resultRow['ip_six'],
                'created_at' => new DateTime($resultRow['created_at']),
                'client_id' => null === $resultRow['client_id'] ? null : (string) $resultRow['client_id'],
            ];
        }

        return $wgPeers;
    }

    /**
     * @return array<array{user_id:string,display_name:string,public_key:string,ip_four:string,ip_six:string,created_at:\DateTime,client_id:string|null}>
     */
    public function wgGetAllPeers(string $profileId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                user_id,
                display_name,
                public_key,
                ip_four,
                ip_six,
                created_at,
                client_id
             FROM
                wg_peers
             WHERE
                profile_id = :profile_id'
        );
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->execute();
        $wgPeers = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $wgPeers[] = [
                'user_id' => (string) $resultRow['user_id'],
                'display_name' => (string) $resultRow['display_name'],
                'public_key' => (string) $resultRow['public_key'],
                'ip_four' => (string) $resultRow['ip_four'],
                'ip_six' => (string) $resultRow['ip_six'],
                'created_at' => new DateTime($resultRow['created_at']),
                'client_id' => null === $resultRow['client_id'] ? null : (string) $resultRow['client_id'],
            ];
        }

        return $wgPeers;
    }

    public function addLocalUser(string $userId, string $userPass, DateTime $createdAt): void
    {
        if ($this->userExists($userId)) {
            $this->updatePassword($userId, $userPass);

            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO
                local_users (user_id, password_hash, created_at)
            VALUES
                (:user_id, :password_hash, :created_at)'
        );

        $passwordHash = password_hash($userPass, \PASSWORD_DEFAULT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
        $stmt->bindValue(':created_at', $createdAt->format(DateTime::ATOM), PDO::PARAM_STR);
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
             FROM local_users
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
                local_users
             SET
                password_hash = :password_hash
             WHERE
                user_id = :user_id'
        );

        $passwordHash = password_hash($newUserPass, \PASSWORD_DEFAULT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function hasAuthorization(string $authKey): bool
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

    public function storeAuthorization(string $userId, string $clientId, string $scope, string $authKey): void
    {
        // the "authorizations" table has the UNIQUE constraint on the
        // "auth_key" column, thus preventing multiple entries with the same
        // "auth_key" to make absolutely sure "auth_keys" cannot be replayed
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
     * @return array<\fkooman\OAuth\Server\Authorization>
     */
    public function getAuthorizations(string $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                auth_key,
                client_id,
                scope
             FROM authorizations
             WHERE
                user_id = :user_id'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, Authorization::class);
    }

    public function deleteAuthorization(string $authKey): void
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

    public function init(): void
    {
        $this->migration->init();
    }

    public function update(): void
    {
        $this->migration->run();
    }

    /**
     * @return array
     */
    public function getUsers()
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
        SELECT
            user_id,
            session_expires_at,
            permission_list,
            is_disabled
        FROM
            users
    SQL
        );
        $stmt->execute();

        $userList = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $userList[] = [
                'user_id' => $row['user_id'],
                'is_disabled' => (bool) $row['is_disabled'],
                'session_expires_at' => $row['session_expires_at'],
                'permission_list' => Json::decode($row['permission_list']),
            ];
        }

        return $userList;
    }

    /**
     * @param string $userId
     *
     * @return scalar|null
     */
    public function getSessionExpiresAt($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
        SELECT
            session_expires_at
        FROM
            users
        WHERE
            user_id = :user_id
    SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    /**
     * @param string $userId
     *
     * @return array<string>
     */
    public function getPermissionList($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
        SELECT
            permission_list
        FROM
            users
        WHERE
            user_id = :user_id
    SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return Json::decode($stmt->fetchColumn());
    }

    /**
     * @return array
     */
    public function getAppUsage()
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
        SELECT
            client_id,
            COUNT(DISTINCT user_id) AS client_count
        FROM
            certificates
        GROUP BY
            client_id
        ORDER BY
            client_count DESC
    SQL
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $commonName
     *
     * @return false|array
     */
    public function getUserCertificateInfo($commonName)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
        SELECT
            u.user_id AS user_id,
            u.is_disabled AS user_is_disabled,
            c.display_name AS display_name,
            c.valid_from,
            c.valid_to,
            c.client_id
        FROM
            users u, certificates c
        WHERE
            u.user_id = c.user_id AND
            c.common_name = :common_name
    SQL
        );

        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $userId
     */
    public function deleteUser($userId): void
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
        DELETE FROM
            users
        WHERE
            user_id = :user_id
    SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string        $userId
     * @param array<string> $permissionList
     */
    public function updateSessionInfo($userId, DateTime $sessionExpiresAt, array $permissionList): void
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
        UPDATE
            users
        SET
            session_expires_at = :session_expires_at,
            permission_list = :permission_list
        WHERE
            user_id = :user_id
    SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':session_expires_at', $sessionExpiresAt->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':permission_list', Json::encode($permissionList), PDO::PARAM_STR);

        $stmt->execute();
    }

    /**
     * @param string      $userId
     * @param string      $commonName
     * @param string      $displayName
     * @param string|null $clientId
     */
    public function addCertificate($userId, $commonName, $displayName, DateTime $validFrom, DateTime $validTo, $clientId): void
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
        INSERT INTO certificates
            (common_name, user_id, display_name, valid_from, valid_to, client_id)
        VALUES
            (:common_name, :user_id, :display_name, :valid_from, :valid_to, :client_id)
    SQL
        );
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':display_name', $displayName, PDO::PARAM_STR);
        $stmt->bindValue(':valid_from', $validFrom->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':valid_to', $validTo->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR | PDO::PARAM_NULL);
        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return array
     */
    public function getCertificates($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
        SELECT
            common_name,
            display_name,
            valid_from,
            valid_to,
            client_id
        FROM
            certificates
        WHERE
            user_id = :user_id
        ORDER BY
            valid_from DESC
    SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $commonName
     */
    public function deleteCertificate($commonName): void
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
        DELETE FROM
            certificates
        WHERE
            common_name = :common_name
    SQL
        );
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $userId
     * @param string $clientId
     */
    public function deleteCertificatesOfClientId($userId, $clientId): void
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
        DELETE FROM
            certificates
        WHERE
            user_id = :user_id
        AND
            client_id = :client_id
    SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $userId
     */
    public function disableUser($userId): void
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
        UPDATE
            users
        SET
            is_disabled = 1
        WHERE
            user_id = :user_id
    SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $userId
     */
    public function enableUser($userId): void
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
        UPDATE
            users
        SET
            is_disabled = 0
        WHERE
            user_id = :user_id
    SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return bool
     */
    public function isDisabledUser($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
        SELECT
            is_disabled
        FROM
            users
        WHERE
            user_id = :user_id
    SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        // because the user always exists, this will always return something,
        // this is why we don't need to distinguish between a successful fetch
        // or not, a bit ugly!
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param string $profileId
     * @param string $commonName
     * @param string $ip4
     * @param string $ip6
     */
    public function clientConnect($profileId, $commonName, $ip4, $ip6, DateTime $connectedAt): void
    {
        // update "lost" client entries when a new client connects that gets
        // the IP address of an existing entry that was not "closed" yet. This
        // may occur when the OpenVPN process dies without writing the
        // disconnect event to the log. We fix this when a new client
        // wants to connect and gets this exact same IP address...
        $stmt = $this->db->prepare(
<<< 'SQL'
            UPDATE
                connection_log
            SET
                disconnected_at = :date_time,
                client_lost = 1
            WHERE
                profile_id = :profile_id
            AND
                ip4 = :ip4
            AND
                ip6 = :ip6
            AND
                disconnected_at IS NULL
    SQL
        );

        $stmt->bindValue(':date_time', $connectedAt->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':ip4', $ip4, PDO::PARAM_STR);
        $stmt->bindValue(':ip6', $ip6, PDO::PARAM_STR);
        $stmt->execute();

        // this query is so complex, because we want to store the user_id in the
        // log as well, not just the common_name... the user may delete the
        // certificate, or the user account may be deleted...
        $stmt = $this->db->prepare(
<<< 'SQL'
        INSERT INTO connection_log
            (
                user_id,
                profile_id,
                common_name,
                ip4,
                ip6,
                connected_at
            )
        VALUES
            (
                (
                    SELECT
                        u.user_id
                    FROM
                        users u, certificates c
                    WHERE
                        u.user_id = c.user_id
                    AND
                        c.common_name = :common_name
                ),
                :profile_id,
                :common_name,
                :ip4,
                :ip6,
                :connected_at
            )
    SQL
        );

        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':ip4', $ip4, PDO::PARAM_STR);
        $stmt->bindValue(':ip6', $ip6, PDO::PARAM_STR);
        $stmt->bindValue(':connected_at', $connectedAt->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $profileId
     * @param string $commonName
     * @param string $ip4
     * @param string $ip6
     * @param int    $bytesTransferred
     */
    public function clientDisconnect($profileId, $commonName, $ip4, $ip6, DateTime $connectedAt, DateTime $disconnectedAt, $bytesTransferred): void
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
        UPDATE
            connection_log
        SET
            disconnected_at = :disconnected_at,
            bytes_transferred = :bytes_transferred
        WHERE
            profile_id = :profile_id
        AND
            common_name = :common_name
        AND
            ip4 = :ip4
        AND
            ip6 = :ip6
        AND
            connected_at = :connected_at
    SQL
        );

        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':ip4', $ip4, PDO::PARAM_STR);
        $stmt->bindValue(':ip6', $ip6, PDO::PARAM_STR);
        $stmt->bindValue(':connected_at', $connectedAt->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':disconnected_at', $disconnectedAt->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':bytes_transferred', $bytesTransferred, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return array
     */
    public function getConnectionLogForUser($userId)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
        SELECT
            l.user_id,
            l.common_name,
            l.profile_id,
            l.ip4,
            l.ip6,
            l.connected_at,
            l.disconnected_at,
            l.bytes_transferred,
            l.client_lost,
            c.client_id AS client_id
        FROM
            connection_log l,
            certificates c
        WHERE
            l.user_id = :user_id
        AND
            l.common_name = c.common_name
        ORDER BY
            l.connected_at
        DESC
    SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $ipAddress
     *
     * @return false|array
     */
    public function getLogEntry(DateTime $dateTime, $ipAddress)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
        SELECT
            user_id,
            profile_id,
            common_name,
            ip4,
            ip6,
            connected_at,
            disconnected_at,
            client_lost
        FROM
            connection_log
        WHERE
            (ip4 = :ip_address OR ip6 = :ip_address)
        AND
            connected_at < :date_time
        AND
            (disconnected_at > :date_time OR disconnected_at IS NULL)
    SQL
        );
        $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $stmt->bindValue(':date_time', $dateTime->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->execute();

        // XXX can this also contain multiple results? I don't think so, but
        // make sure!
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function cleanConnectionLog(DateTime $dateTime): void
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
        DELETE FROM
            connection_log
        WHERE
            connected_at < :date_time
        AND
            disconnected_at IS NOT NULL
    SQL
        );

        $stmt->bindValue(':date_time', $dateTime->format(DateTime::ATOM), PDO::PARAM_STR);

        $stmt->execute();
    }

    public function cleanUserMessages(DateTime $dateTime): void
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
        DELETE FROM
            user_messages
        WHERE
            date_time < :date_time
    SQL
        );

        $stmt->bindValue(':date_time', $dateTime->format(DateTime::ATOM), PDO::PARAM_STR);

        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return array
     */
    public function userMessages($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
        SELECT
            id, type, message, date_time
        FROM
            user_messages
        WHERE
            user_id = :user_id
        ORDER BY
            date_time DESC
    SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addUserMessage(string $userId, string $type, string $message, DateTime $dateTime): void
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
        INSERT INTO user_messages
            (user_id, type, message, date_time)
        VALUES
            (:user_id, :type, :message, :date_time)
    SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':date_time', $dateTime->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->execute();
    }

    public function cleanExpiredCertificates(DateTime $dateTime): void
    {
        $stmt = $this->db->prepare('DELETE FROM certificates WHERE valid_to < :date_time');
        $stmt->bindValue(':date_time', $dateTime->format(DateTime::ATOM), PDO::PARAM_STR);

        $stmt->execute();
    }

    /**
     * @return \PDO
     */
    public function getPdo()
    {
        return $this->db;
    }

    /**
     * @param string $userId
     */
    private function addUser($userId): void
    {
        // XXX do something better with session_expires_at
        // also rename it to something better!
        $dateTime = new DateTime();
        $stmt = $this->db->prepare(
<<< 'SQL'
        SELECT
            COUNT(*)
        FROM
            users
        WHERE user_id = :user_id
    SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        if (1 !== (int) $stmt->fetchColumn()) {
            // user does not exist yet
            $stmt = $this->db->prepare(
<<< 'SQL'
        INSERT INTO
            users (
                user_id,
                session_expires_at,
                permission_list,
                is_disabled
            )
        VALUES (
            :user_id,
            :session_expires_at,
            :permission_list,
            :is_disabled
        )
    SQL
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
            $stmt->bindValue(':session_expires_at', $dateTime->format(DateTime::ATOM), PDO::PARAM_STR);
            $stmt->bindValue(':permission_list', '[]', PDO::PARAM_STR);
            $stmt->bindValue(':is_disabled', false, PDO::PARAM_BOOL);
            $stmt->execute();
        }
    }
}
