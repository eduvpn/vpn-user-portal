<?php

namespace fkooman\VPN\UserPortal;

use PDO;
use RuntimeException;
use InvalidArgumentException;

class PdoStorage
{
    /** @var PDO */
    private $db;

    /** @var string */
    private $prefix;

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
                'SELECT user_id, name, status, created_at FROM %s WHERE status != 30 ORDER BY created_at DESC',
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

    public function getConfigurations($userId, $status = self::STATUS_ACTIVE)
    {
        switch ($status) {
            case self::STATUS_ACTIVE:
                $query = sprintf('SELECT user_id, name, created_at FROM %s WHERE user_id = :user_id AND status = :status ORDER BY created_at DESC',
                    $this->prefix.'config'
                );
                break;
            case self::STATUS_REVOKED:
                $query = sprintf('SELECT user_id, name, created_at, revoked_at FROM %s WHERE user_id = :user_id AND status = :status ORDER BY revoked_at DESC',
                    $this->prefix.'config'
                );
                break;
            default:
                throw new InvalidArgumentException('invalid status');
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (false !== $result) {
            return $result;
        }

        return;
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

    public function addConfiguration($userId, $name)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (user_id, name, status, created_at) VALUES(:user_id, :name, :status, :created_at)',
                $this->prefix.'config'
            )
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':status', self::STATUS_ACTIVE, PDO::PARAM_INT);
        $stmt->bindValue(':created_at', time(), PDO::PARAM_INT);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to add configuration');
        }
    }

    public function revokeConfiguration($userId, $name)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'UPDATE %s SET status = :status, revoked_at = :revoked_at WHERE user_id = :user_id AND name = :name',
                $this->prefix.'config'
            )
        );
        $stmt->bindValue(':status', self::STATUS_REVOKED, PDO::PARAM_INT);
        $stmt->bindValue(':revoked_at', time(), PDO::PARAM_INT);
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
                created_at INTEGER NOT NULL,
                revoked_at INTEGER DEFAULT NULL,
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
