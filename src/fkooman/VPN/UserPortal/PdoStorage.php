<?php

namespace fkooman\VPN\UserPortal;

use PDO;
use RuntimeException;

class PdoStorage
{
    /** @var PDO */
    private $db;

    /** @var string */
    private $prefix;

    /** ready for download */
    const STATUS_READY = 10;

    /** already downloaded */
    const STATUS_ACTIVE = 20;

    /** revoked */
    const STATUS_REVOKED = 30;

    public function __construct(PDO $db, $prefix = '')
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
        $this->prefix = $prefix;
    }

    public function getUsers()
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT DISTINCT(user_id) FROM %s ORDER BY user_id',
                $this->prefix.'config'
            )
        );
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // FIXME: returns empty array when no configurations or still false?
        if (false !== $result) {
            return $result;
        }

        return;
    }

    public function getBlockedUsers()
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT user_id FROM %s ORDER BY user_id',
                $this->prefix.'blocked_users'
            )
        );
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // FIXME: returns empty array when no configurations or still false?
        if (false !== $result) {
            return $result;
        }

        return;
    }

    public function isBlocked($userId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT user_id FROM %s WHERE user_id = :user_id',
                $this->prefix.'blocked_users'
            )
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return false !== $stmt->fetch();
    }

    public function blockUser($userId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (user_id) VALUES(:user_id)',
                $this->prefix.'blocked_users'
            )
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to block user');
        }
    }

    public function unblockUser($userId)
    {
        // get all active configuratoins
        $stmt = $this->db->prepare(
            sprintf(
                'DELETE FROM %s WHERE user_id = :user_id',
                $this->prefix.'blocked_users'
            )
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to unblock user');
        }
    }

    public function getAllConfigurations()
    {
        // get all active configuratoins
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT user_id, name, status FROM %s WHERE status != 30 ORDER BY user_id',
                $this->prefix.'config'
            )
        );
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // FIXME: returns empty array when no configurations or still false?
        if (false !== $result) {
            return $result;
        }

        return;
    }

    public function getConfigurations($userId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT name, status FROM %s WHERE user_id = :user_id ORDER BY status, name',
                $this->prefix.'config'
            )
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // FIXME: returns empty array when no configurations or still false?
        if (false !== $result) {
            return $result;
        }

        return;
    }

    public function getConfiguration($userId, $name)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT name, status, config FROM %s WHERE user_id = :user_id AND name = :name',
                $this->prefix.'config'
            )
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function activateConfiguration($userId, $name)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'UPDATE %s SET status = :status, config = :config WHERE user_id = :user_id AND name = :name',
                $this->prefix.'config'
            )
        );
        $stmt->bindValue(':status', self::STATUS_ACTIVE, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':config', null, PDO::PARAM_LOB);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to activate configuration');
        }
    }

    public function isExistingConfiguration($userId, $name)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT name, status FROM %s WHERE user_id = :user_id AND name = :name',
                $this->prefix.'config'
            )
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return false !== $result;
    }

    public function addConfiguration($userId, $name, $configData)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (user_id, name, status, config) VALUES(:user_id, :name, :status, :config)',
                $this->prefix.'config'
            )
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':status', self::STATUS_READY, PDO::PARAM_INT);
        $stmt->bindValue(':config', $configData, PDO::PARAM_LOB);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to add configuration');
        }
    }

    public function revokeConfiguration($userId, $name)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'UPDATE %s SET status = :status WHERE user_id = :user_id AND name = :name',
                $this->prefix.'config'
            )
        );
        $stmt->bindValue(':status', self::STATUS_REVOKED, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to revoke configuration');
        }
    }

    public static function createTableQueries($prefix)
    {
        $query = array();
        $query[] = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                user_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                status INTEGER NOT NULL,
                config BLOB DEFAULT NULL,
                UNIQUE (user_id, name)
            )',
            $prefix.'config'
        );
        $query[] = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                user_id VARCHAR(255) NOT NULL,
                UNIQUE (user_id)
            )',
            $prefix.'blocked_users'
        );

        return $query;
    }

    public function initDatabase()
    {
        $queries = self::createTableQueries($this->prefix);
        foreach ($queries as $q) {
            $this->db->query($q);
        }

        $tables = array('config', 'blocked_users');
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
