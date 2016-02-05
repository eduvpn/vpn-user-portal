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

    public function __construct(PDO $db, $prefix = '')
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
        $this->prefix = $prefix;
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

    public static function createTableQueries($prefix)
    {
        $query = array();
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

        $tables = array('blocked_users');
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
