<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use DateTimeImmutable;
use PDO;

class Storage
{
    public const INCLUDE_EXPIRED = 1;
    public const EXCLUDE_EXPIRED = 2;
    public const EXCLUDE_DISABLED_USER = 4;

    public const CURRENT_SCHEMA_VERSION = '2022011901';

    private PDO $db;

    public function __construct(DbConfig $dbConfig)
    {
        $db = new PDO(
            $dbConfig->dbDsn(),
            $dbConfig->dbUser(),
            $dbConfig->dbPass()
        );
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $dbDriver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ('sqlite' === $dbDriver) {
            $db->exec('PRAGMA foreign_keys = ON');
        }

        // in PHP < 8.1 the ATTR_STRINGIFY_FETCHES attribute was always true,
        // but changed to a default of false in 8.1. Setting this option
        // restores the pre-8.1 behavior. We may switch it to false at some
        // point, but this requires proper testing...
        // @see https://www.php.net/manual/en/migration81.incompatible.php
        $db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);

        // run database initialization/migration if necessary and enabled
        Migration::run(
            $db,
            $dbConfig->schemaDir(),
            self::CURRENT_SCHEMA_VERSION,
            'sqlite' === $dbDriver,     // auto initialization
            'sqlite' === $dbDriver      // auto migration
        );
        $this->db = $db;
    }

    public function dbPdo(): PDO
    {
        return $this->db;
    }

    public function wPeerAdd(string $userId, int $nodeNumber, string $profileId, string $displayName, string $publicKey, string $ipFour, string $ipSix, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt, ?string $authKey): void
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                INSERT
                INTO
                    wg_peers (
                        user_id,
                        node_number,
                        profile_id,
                        display_name,
                        public_key,
                        ip_four,
                        ip_six,
                        created_at,
                        expires_at,
                        auth_key
                    )
                    VALUES(
                        :user_id,
                        :node_number,
                        :profile_id,
                        :display_name,
                        :public_key,
                        :ip_four,
                        :ip_six,
                        :created_at,
                        :expires_at,
                        :auth_key
                    )
                SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':node_number', $nodeNumber, PDO::PARAM_INT);
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':display_name', $displayName, PDO::PARAM_STR);
        $stmt->bindValue(':public_key', $publicKey, PDO::PARAM_STR);
        $stmt->bindValue(':ip_four', $ipFour, PDO::PARAM_STR);
        $stmt->bindValue(':ip_six', $ipSix, PDO::PARAM_STR);
        $stmt->bindValue(':created_at', $createdAt->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $expiresAt->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':auth_key', $authKey ?? null, PDO::PARAM_STR | PDO::PARAM_NULL);
        $stmt->execute();
    }

    public function wPeerRemove(string $userId, string $publicKey): void
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                DELETE
                FROM
                    wg_peers
                WHERE
                    user_id = :user_id
                AND
                    public_key = :public_key
                SQL
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
            <<< 'SQL'
                SELECT
                    ip_four
                FROM
                    wg_peers
                SQL
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return array<array{profile_id:string,display_name:string,public_key:string,expires_at:\DateTimeImmutable,auth_key:?string}>
     */
    public function wPeerListByUserId(string $userId): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                SELECT
                    profile_id,
                    display_name,
                    public_key,
                    expires_at,
                    auth_key
                FROM
                    wg_peers
                WHERE
                    user_id = :user_id
                ORDER BY
                    expires_at DESC
                SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        $peerList = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $peerList[] = [
                'profile_id' => (string) $resultRow['profile_id'],
                'display_name' => (string) $resultRow['display_name'],
                'public_key' => (string) $resultRow['public_key'],
                'expires_at' => Dt::get($resultRow['expires_at']),
                'auth_key' => null === $resultRow['auth_key'] ? null : (string) $resultRow['auth_key'],
            ];
        }

        return $peerList;
    }

    /**
     * @return array<array{user_id:string,profile_id:string,public_key:string}>
     */
    public function wPeerListByAuthKey(string $authKey): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                SELECT
                    user_id,
                    profile_id,
                    public_key
                FROM
                    wg_peers
                WHERE
                    auth_key = :auth_key
                SQL
        );
        $stmt->bindValue(':auth_key', $authKey, PDO::PARAM_STR);
        $stmt->execute();
        $peerList = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $peerList[] = [
                'user_id' => (string) $resultRow['user_id'],
                'profile_id' => (string) $resultRow['profile_id'],
                'public_key' => (string) $resultRow['public_key'],
            ];
        }

        return $peerList;
    }

    /**
     * @return array<string,array{user_id:string,profile_id:string,display_name:string,public_key:string,ip_four:string,ip_six:string,expires_at:\DateTimeImmutable,auth_key:?string}>
     */
    public function wPeerListByProfileId(string $profileId, int $returnSet): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                SELECT
                    u.user_id,
                    profile_id,
                    display_name,
                    public_key,
                    ip_four,
                    ip_six,
                    expires_at,
                    auth_key,
                    u.is_disabled AS user_is_disabled
                FROM
                    wg_peers w,
                    users u
                WHERE
                    profile_id = :profile_id
                AND
                    u.user_id = w.user_id
                SQL
        );
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->execute();
        $peerList = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $expiresAt = Dt::get($resultRow['expires_at']);
            if (self::EXCLUDE_EXPIRED & $returnSet) {
                // exclude disabled configurations
                if ($expiresAt <= Dt::get()) {
                    continue;
                }
            }
            if (self::EXCLUDE_DISABLED_USER & $returnSet) {
                // exclude peer configurations for disabled users
                if (1 === (int) $resultRow['user_is_disabled']) {
                    continue;
                }
            }
            $publicKey = (string) $resultRow['public_key'];
            $peerList[$publicKey] = [
                'user_id' => (string) $resultRow['user_id'],
                'profile_id' => (string) $resultRow['profile_id'],
                'display_name' => (string) $resultRow['display_name'],
                'public_key' => $publicKey,
                'ip_four' => (string) $resultRow['ip_four'],
                'ip_six' => (string) $resultRow['ip_six'],
                'expires_at' => $expiresAt,
                'auth_key' => null === $resultRow['auth_key'] ? null : (string) $resultRow['auth_key'],
            ];
        }

        return $peerList;
    }

    public function localUserExists(string $authUser): bool
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                SELECT
                    COUNT(user_id)
                FROM
                    local_users
                WHERE
                    user_id = :user_id
                SQL
        );

        $stmt->bindValue(':user_id', $authUser, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === (int) $stmt->fetchColumn();
    }

    public function localUserAdd(string $userId, string $passwordHash, DateTimeImmutable $createdAt): void
    {
        if ($this->localUserExists($userId)) {
            $this->localUserUpdatePassword($userId, $passwordHash);

            return;
        }

        $stmt = $this->db->prepare(
            <<< 'SQL'
                INSERT
                INTO
                    local_users (
                        user_id,
                        password_hash,
                        created_at
                    )
                    VALUES (
                        :user_id,
                        :password_hash,
                        :created_at
                    )
                SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
        $stmt->bindValue(':created_at', $createdAt->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->execute();
    }

    public function localUserDelete(string $userId): void
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    DELETE FROM
                        local_users
                    WHERE
                        user_id = :user_id
                SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function localUserUpdatePassword(string $userId, string $passwordHash): void
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                UPDATE
                    local_users
                SET
                    password_hash = :password_hash
                WHERE
                    user_id = :user_id
                SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function localUserPasswordHash(string $authUser): ?string
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                SELECT
                    password_hash
                FROM
                    local_users
                WHERE
                    user_id = :user_id
                SQL
        );

        $stmt->bindValue(':user_id', $authUser, PDO::PARAM_STR);
        $stmt->execute();
        $resultColumn = $stmt->fetchColumn(0);

        return \is_string($resultColumn) ? $resultColumn : null;
    }

    /**
     * @return array<array{user_id:string,last_seen:\DateTimeImmutable,permission_list:array<string>,is_disabled:bool}>
     */
    public function userList(): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        user_id,
                        last_seen,
                        permission_list,
                        is_disabled
                    FROM
                        users
                    ORDER BY
                        user_id
                SQL
        );
        $stmt->execute();

        $userList = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $userList[] = [
                'user_id' => (string) $resultRow['user_id'],
                'last_seen' => Dt::get($resultRow['last_seen']),
                'permission_list' => self::stringToPermissionList((string) $resultRow['permission_list']),
                'is_disabled' => (bool) $resultRow['is_disabled'],
            ];
        }

        return $userList;
    }

    /**
     * @return array<string>
     */
    public function userPermissionList(string $userId): array
    {
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

        // XXX what if user does not (yet) exist, later also for guest scenario?
        return self::stringToPermissionList((string) $stmt->fetchColumn());
    }

    public function userDelete(string $userId): void
    {
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
     * @param array<string> $permissionList
     */
    public function userUpdate(string $userId, DateTimeImmutable $lastSeen, array $permissionList): void
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    UPDATE
                        users
                    SET
                        last_seen = :last_seen,
                        permission_list = :permission_list
                    WHERE
                        user_id = :user_id
                SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':last_seen', $lastSeen->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':permission_list', self::permissionListToString($permissionList), PDO::PARAM_STR);

        $stmt->execute();
    }

    public function oCertAdd(string $userId, int $nodeNumber, string $profileId, string $commonName, string $displayName, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt, ?string $authKey): void
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    INSERT INTO certificates
                        (node_number, profile_id, common_name, user_id, display_name, created_at, expires_at, auth_key)
                    VALUES
                        (:node_number, :profile_id, :common_name, :user_id, :display_name, :created_at, :expires_at, :auth_key)
                SQL
        );
        $stmt->bindValue(':node_number', $nodeNumber, PDO::PARAM_INT);
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':display_name', $displayName, PDO::PARAM_STR);
        $stmt->bindValue(':created_at', $createdAt->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $expiresAt->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':auth_key', $authKey ?? null, PDO::PARAM_STR | PDO::PARAM_NULL);
        $stmt->execute();
    }

    /**
     * @return array<array{user_id:string,profile_id:string,common_name:string}>
     */
    public function oCertListByAuthKey(string $authKey): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        user_id,
                        profile_id,
                        common_name
                    FROM
                        certificates
                    WHERE
                        auth_key = :auth_key
                SQL
        );
        $stmt->bindValue(':auth_key', $authKey, PDO::PARAM_STR);
        $stmt->execute();

        $certList = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $certList[] = [
                'user_id' => (string) $resultRow['user_id'],
                'profile_id' => (string) $resultRow['profile_id'],
                'common_name' => (string) $resultRow['common_name'],
            ];
        }

        return $certList;
    }

    /**
     * @return array<string,array{user_id:string,common_name:string,display_name:string,expires_at:\DateTimeImmutable,auth_key:?string}>
     */
    public function oCertListByProfileId(string $profileId, int $returnSet): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        user_id,
                        common_name,
                        display_name,
                        expires_at,
                        auth_key
                    FROM
                        certificates
                    WHERE
                        profile_id = :profile_id
                SQL
        );
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->execute();

        $certList = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $expiresAt = Dt::get($resultRow['expires_at']);
            if (self::EXCLUDE_EXPIRED === $returnSet) {
                if ($expiresAt <= Dt::get()) {
                    continue;
                }
            }

            $commonName = (string) $resultRow['common_name'];
            $certList[$commonName] = [
                'user_id' => (string) $resultRow['user_id'],
                'common_name' => $commonName,
                'display_name' => (string) $resultRow['display_name'],
                'expires_at' => $expiresAt,
                'auth_key' => null === $resultRow['auth_key'] ? null : (string) $resultRow['auth_key'],
            ];
        }

        return $certList;
    }

    /**
     * @return array<array{profile_id:string,common_name:string,display_name:string,expires_at:\DateTimeImmutable,auth_key:?string}>
     */
    public function oCertListByUserId(string $userId): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        profile_id,
                        common_name,
                        display_name,
                        expires_at,
                        auth_key
                    FROM
                        certificates
                    WHERE
                        user_id = :user_id
                    ORDER BY
                        expires_at DESC
                SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        $certList = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $certList[] = [
                'profile_id' => (string) $resultRow['profile_id'],
                'common_name' => (string) $resultRow['common_name'],
                'display_name' => (string) $resultRow['display_name'],
                'expires_at' => Dt::get($resultRow['expires_at']),
                'auth_key' => null === $resultRow['auth_key'] ? null : (string) $resultRow['auth_key'],
            ];
        }

        return $certList;
    }

    /**
     * @return ?array{user_id:string,user_is_disabled:bool}
     */
    public function oUserInfoByProfileIdAndCommonName(string $profileId, string $commonName): ?array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        u.user_id AS user_id,
                        u.is_disabled AS user_is_disabled
                    FROM
                        users u, certificates c
                    WHERE
                        u.user_id = c.user_id
                    AND
                        c.profile_id = :profile_id
                    AND
                        c.common_name = :common_name
                SQL
        );

        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->execute();

        if (false === $resultRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return null;
        }

        return [
            'user_id' => (string) $resultRow['user_id'],
            'user_is_disabled' => (bool) $resultRow['user_is_disabled'],
        ];
    }

    public function oCertDelete(string $userId, string $commonName): void
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    DELETE FROM
                        certificates
                    WHERE
                        user_id = :user_id
                    AND
                        common_name = :common_name
                SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function userDisable(string $userId): void
    {
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

    public function userEnable(string $userId): void
    {
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

    public function userExists(string $userId): bool
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        COUNT(user_id)
                    FROM
                        users
                    WHERE
                        user_id = :user_id
                SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === (int) $stmt->fetchColumn(0);
    }

    public function userIsDisabled(string $userId): bool
    {
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
        return (bool) $stmt->fetchColumn(0);
    }

    public function clientConnect(string $userId, string $profileId, string $vpnProto, string $connectionId, string $ipFour, string $ipSix, DateTimeImmutable $connectedAt): void
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    INSERT INTO connection_log
                        (
                            user_id,
                            profile_id,
                            vpn_proto,
                            connection_id,
                            ip_four,
                            ip_six,
                            connected_at
                        )
                    VALUES
                        (
                            :user_id,
                            :profile_id,
                            :vpn_proto,
                            :connection_id,
                            :ip_four,
                            :ip_six,
                            :connected_at
                        )
                SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':vpn_proto', $vpnProto, PDO::PARAM_STR);
        $stmt->bindValue(':connection_id', $connectionId, PDO::PARAM_STR);
        $stmt->bindValue(':ip_four', $ipFour, PDO::PARAM_STR);
        $stmt->bindValue(':ip_six', $ipSix, PDO::PARAM_STR);
        $stmt->bindValue(':connected_at', $connectedAt->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->execute();
    }

    public function wNodeNumber(string $userId, string $profileId, string $connectionId): ?int
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                SELECT
                    node_number
                FROM
                    wg_peers
                WHERE
                    user_id = :user_id
                AND
                    profile_id = :profile_id
                AND
                    public_key = :public_key
                SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':public_key', $connectionId, PDO::PARAM_STR);

        $stmt->execute();

        if (false === $resultColumn = $stmt->fetchColumn(0)) {
            return null;
        }

        return (int) $resultColumn;
    }

    public function oNodeNumber(string $userId, string $profileId, string $connectionId): ?int
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                SELECT
                    node_number
                FROM
                    certificates
                WHERE
                    user_id = :user_id
                AND
                    profile_id = :profile_id
                AND
                    common_name = :common_name
                SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':common_name', $connectionId, PDO::PARAM_STR);

        $stmt->execute();

        if (false === $resultColumn = $stmt->fetchColumn(0)) {
            return null;
        }

        return (int) $resultColumn;
    }

    public function clientDisconnect(string $userId, string $profileId, string $connectionId, int $bytesIn, int $bytesOut, DateTimeImmutable $disconnectedAt): void
    {
        // XXX make sure the entry with disconnected_at IS NULL exists, otherwise scream
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    UPDATE
                        connection_log
                    SET
                        bytes_in = :bytes_in,
                        bytes_out = :bytes_out,
                        disconnected_at = :disconnected_at
                    WHERE
                        user_id = :user_id
                    AND
                        profile_id = :profile_id
                    AND
                        connection_id = :connection_id
                    AND
                        disconnected_at IS NULL
                SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':connection_id', $connectionId, PDO::PARAM_STR);
        $stmt->bindValue(':disconnected_at', $disconnectedAt->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':bytes_in', $bytesIn, PDO::PARAM_INT);
        $stmt->bindValue(':bytes_out', $bytesOut, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function oUserIdFromConnectionLog(string $profileId, string $commonName): ?string
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        user_id
                    FROM
                        connection_log
                    WHERE
                        profile_id = :profile_id
                    AND
                        connection_id = :connection_id
                    AND
                        disconnected_at IS NULL
                SQL
        );

        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':connection_id', $commonName, PDO::PARAM_STR);
        $stmt->execute();

        if (false === $resultColumn = $stmt->fetchColumn(0)) {
            return null;
        }

        return (string) $resultColumn;
    }

    /**
     * @return array<array{profile_id:string,ip_four:string,ip_six:string,connected_at:\DateTimeImmutable,disconnected_at:?\DateTimeImmutable}>
     */
    public function getConnectionLogForUser(string $userId): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        profile_id,
                        ip_four,
                        ip_six,
                        connected_at,
                        disconnected_at
                    FROM
                        connection_log
                    WHERE
                        user_id= :user_id
                    ORDER BY
                        connected_at
                    DESC
                SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        $connectionLog = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $connectionLog[] = [
                'profile_id' => (string) $resultRow['profile_id'],
                'ip_four' => (string) $resultRow['ip_four'],
                'ip_six' => (string) $resultRow['ip_six'],
                'connected_at' => Dt::get((string) $resultRow['connected_at']),
                'disconnected_at' => null === $resultRow['disconnected_at'] ? null : Dt::get((string) $resultRow['disconnected_at']),
            ];
        }

        return $connectionLog;
    }

    /**
     * @return array<array{user_id:string,profile_id:string,ip_four:string,ip_six:string,connected_at:\DateTimeImmutable,disconnected_at:?\DateTimeImmutable}>
     */
    public function getLogEntries(DateTimeImmutable $dateTime, string $ipAddress): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        user_id,
                        profile_id,
                        ip_four,
                        ip_six,
                        connected_at,
                        disconnected_at
                    FROM
                        connection_log
                    WHERE
                        (ip_four = :ip_address OR ip_six = :ip_address)
                    AND
                        connected_at <= :date_time
                    AND
                        (disconnected_at IS NULL OR disconnected_at >= :date_time)
                SQL
        );

        $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $stmt->bindValue(':date_time', $dateTime->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->execute();
        $logEntries = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $logEntries[] = [
                'user_id' => (string) $resultRow['user_id'],
                'profile_id' => (string) $resultRow['profile_id'],
                'ip_four' => (string) $resultRow['ip_four'],
                'ip_six' => (string) $resultRow['ip_six'],
                'connected_at' => Dt::get((string) $resultRow['connected_at']),
                'disconnected_at' => null === $resultRow['disconnected_at'] ? null : Dt::get((string) $resultRow['disconnected_at']),
            ];
        }

        return $logEntries;
    }

    public function cleanConnectionLog(DateTimeImmutable $dateTime): void
    {
        // XXX test this!
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    DELETE FROM
                        connection_log
                    WHERE
                        disconnected_at IS NOT NULL
                    AND
                        disconnected_at < :date_time
                SQL
        );

        $stmt->bindValue(':date_time', $dateTime->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->execute();
    }

    public function cleanLiveStats(DateTimeImmutable $dateTime): void
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    DELETE FROM
                        live_stats
                    WHERE
                        date_time < :date_time
                SQL
        );

        $stmt->bindValue(':date_time', $dateTime->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->execute();
    }

    public function cleanExpiredConfigurations(DateTimeImmutable $dateTime): void
    {
        $stmt = $this->db->prepare('DELETE FROM certificates WHERE expires_at < :date_time');
        $stmt->bindValue(':date_time', $dateTime->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->execute();

        $stmt = $this->db->prepare('DELETE FROM wg_peers WHERE expires_at < :date_time');
        $stmt->bindValue(':date_time', $dateTime->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Get the non-expired WireGuard and OpenVPN *API* configurations for a
     * particular user.
     *
     * @return array<array{profile_id:string,connection_id:string}>
     */
    public function activeApiConfigurations(string $userId, DateTimeImmutable $dateTime): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                SELECT
                    profile_id, common_name AS connection_id, created_at
                FROM
                    certificates
                WHERE
                    user_id = :user_id
                AND
                    auth_key IS NOT NULL
                AND
                    expires_at > :date_time
                UNION
                SELECT
                    profile_id, public_key AS connection_id, created_at
                FROM
                    wg_peers
                WHERE
                    user_id = :user_id
                AND
                    auth_key IS NOT NULL
                AND
                    expires_at > :date_time
                ORDER BY
                    created_at
                SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':date_time', $dateTime->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->execute();

        $activeApiConfigurations = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $activeApiConfigurations[] = [
                'profile_id' => (string) $resultRow['profile_id'],
                'connection_id' => (string) $resultRow['connection_id'],
            ];
        }

        return $activeApiConfigurations;
    }

    /**
     * Get the number of non-expired WireGuard and OpenVPN *portal*
     * configurations for a particular user.
     */
    public function numberOfActivePortalConfigurations(string $userId, DateTimeImmutable $dateTime): int
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                        SELECT SUM(c)
                        FROM (
                            SELECT
                                COUNT(public_key) AS c
                            FROM
                                wg_peers
                            WHERE
                                user_id = :user_id
                            AND
                                auth_key IS NULL
                            AND
                                expires_at > :date_time
                        UNION ALL
                            SELECT
                                COUNT(common_name) AS c
                            FROM
                                certificates
                            WHERE
                                user_id = :user_id
                            AND
                                auth_key IS NULL
                            AND
                                expires_at > :date_time
                        ) AS c
                SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':date_time', $dateTime->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function cleanExpiredOAuthAuthorizations(DateTimeImmutable $dateTime): void
    {
        // XXX is this still needed or already done by the OAuth library?!
        $stmt = $this->db->prepare('DELETE FROM oauth_authorizations WHERE expires_at < :date_time');
        $stmt->bindValue(':date_time', $dateTime->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);

        $stmt->execute();
    }

    /**
     * @param array<string> $permissionList
     */
    public function userAdd(string $userId, DateTimeImmutable $lastSeen, array $permissionList): void
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    INSERT INTO
                        users (
                            user_id,
                            last_seen,
                            permission_list,
                            is_disabled
                        )
                    VALUES (
                        :user_id,
                        :last_seen,
                        :permission_list,
                        :is_disabled
                    )
                SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':last_seen', $lastSeen->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':permission_list', self::permissionListToString($permissionList), PDO::PARAM_STR);
        $stmt->bindValue(':is_disabled', false, PDO::PARAM_BOOL);
        $stmt->execute();
    }

    public function statsAdd(DateTimeImmutable $dateTime, string $profileId, int $connectionCount): void
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    INSERT INTO
                        live_stats (
                            date_time,
                            profile_id,
                            connection_count
                        )
                    VALUES (
                        :date_time,
                        :profile_id,
                        :connection_count
                    )
                SQL
        );
        $stmt->bindValue(':date_time', $dateTime->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':connection_count', $connectionCount, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @return array<array{date_time:\DateTimeImmutable,connection_count:int}>
     */
    public function statsGetLive(string $profileId): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        date_time,
                        connection_count
                    FROM
                        live_stats
                    WHERE
                        profile_id = :profile_id
                SQL
        );
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->execute();

        $statsData = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $statsData[] = [
                'date_time' => Dt::get($resultRow['date_time']),
                'connection_count' => (int) $resultRow['connection_count'],
            ];
        }

        return $statsData;
    }

    /**
     * @return array<string,array{max_connection_count:int}>
     */
    public function statsGetLiveMaxConnectionCount(): array
    {
        // XXX housekeeping will remove all entries older than 1 week, so I
        // guess we do not have to limit here...
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        profile_id,
                        MAX(connection_count) AS max_connection_count
                    FROM
                        live_stats
                    GROUP BY
                        profile_id
                SQL
        );
        $stmt->execute();
        $statsData = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $statsData[(string) $resultRow['profile_id']] = [
                'max_connection_count' => (int) $resultRow['max_connection_count'],
            ];
        }

        return $statsData;
    }

    /**
     * @return array<string,array{unique_user_count:int}>
     */
    public function statsGetUniqueUsers(DateTimeImmutable $dateTime): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                SELECT
                    profile_id,
                    COUNT(DISTINCT user_id) AS unique_user_count
                FROM
                    connection_log
                WHERE
                    connected_at >= :date_time
                GROUP BY
                    profile_id

                SQL
        );
        $stmt->bindValue(':date_time', $dateTime->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->execute();
        $statsData = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $statsData[(string) $resultRow['profile_id']] = [
                'unique_user_count' => (int) $resultRow['unique_user_count'],
            ];
        }

        return $statsData;
    }

    /**
     * @return array<array{date:string,unique_user_count:int,max_connection_count:int}>
     */
    public function statsGetAggregate(string $profileId): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        date,
                        unique_user_count,
                        max_connection_count
                    FROM
                        aggregate_stats
                    WHERE
                        profile_id = :profile_id
                SQL
        );
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->execute();
        $statsData = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
            $statsData[] = [
                'date' => (string) $resultRow['date'],
                'unique_user_count' => (int) $resultRow['unique_user_count'],
                'max_connection_count' => (int) $resultRow['max_connection_count'],
            ];
        }

        return $statsData;
    }

    public function statsAggregate(DateTimeImmutable $dateTime): void
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                INSERT INTO
                    aggregate_stats
                SELECT
                    DATE(l.date_time) AS date,
                    l.profile_id AS profile_id,
                    MAX(l.connection_count) AS max_connection_count,
                    COUNT(DISTINCT c.user_id) AS unique_user_count
                FROM
                    live_stats l
                LEFT JOIN
                    connection_log c
                ON
                    DATE(l.date_time) = DATE(c.connected_at)
                AND
                    l.profile_id = c.profile_id
                WHERE
                    l.date_time < :date_time
                GROUP BY
                    date,
                    l.profile_id
                SQL
        );
        $stmt->bindValue(':date_time', $dateTime->format(DateTimeImmutable::ATOM), PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @return array<array{client_id:string,client_count:int}>
     */
    public function appUsage(): array
    {
        $stmt = $this->db->prepare(
            <<< 'SQL'
                    SELECT
                        client_id,
                        COUNT(DISTINCT client_id) AS client_count
                    FROM
                        oauth_authorizations
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
     * @param array<string> $permissionList
     */
    private static function permissionListToString(array $permissionList): string
    {
        // XXX we MUST make sure permissionList does not ever contain a "|"
        return implode('|', $permissionList);
    }

    /**
     * @return array<string>
     */
    private static function stringToPermissionList(string $string): array
    {
        if ('' === $string) {
            return [];
        }

        return explode('|', $string);
    }
}
