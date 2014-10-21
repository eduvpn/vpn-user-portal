<?php

namespace fkooman\VpnPortal;

use PDO;
use fkooman\VpnPortal\Exception\PdoStorageException;

class PdoStorage
{
    private $db;
    private $prefix;

    public function __construct(PDO $db, $prefix = "")
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
        $this->prefix = $prefix;
    }

    public function getConfigurations($userId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                "SELECT name, status FROM %s WHERE user_id = :user_id ORDER BY status, name",
                $this->prefix.'config'
            )
        );
        $stmt->bindValue(":user_id", $userId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // FIXME: returns empty array when no configurations or still false?
        if (false !== $result) {
            return $result;
        }

        return null;
    }

    public function isExistingConfiguration($userId, $name)
    {
        $stmt = $this->db->prepare(
            sprintf(
                "SELECT name, status FROM %s WHERE user_id = :user_id AND name = :name",
                $this->prefix.'config'
            )
        );
        $stmt->bindValue(":user_id", $userId, PDO::PARAM_STR);
        $stmt->bindValue(":name", $name, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return false !== $result;
    }

    public function addConfiguration($userId, $name)
    {
        $stmt = $this->db->prepare(
            sprintf(
                "INSERT INTO %s (user_id, name, status) VALUES(:user_id, :name, :status)",
                $this->prefix.'config'
            )
        );
        $stmt->bindValue(":user_id", $userId, PDO::PARAM_STR);
        $stmt->bindValue(":name", $name, PDO::PARAM_STR);
        $stmt->bindValue(":status", "active", PDO::PARAM_STR);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new PdoStorageException("unable to add configuration");
        }
    }

    public function revokeConfiguration($userId, $name)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'UPDATE %s SET status = "revoked" WHERE user_id = :user_id AND name = :name',
                $this->prefix.'config'
            )
        );
        $stmt->bindValue(":user_id", $userId, PDO::PARAM_STR);
        $stmt->bindValue(":name", $name, PDO::PARAM_STR);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new PdoStorageException("unable to revoke configuration");
        }
    }

    public static function createTableQueries($prefix)
    {
        $query = array();
        $query[] = sprintf(
            "CREATE TABLE IF NOT EXISTS %s (
                user_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                status VARCHAR(255) NOT NULL,
                UNIQUE (user_id, name)
            )",
            $prefix.'config'
        );

        return $query;
    }

    public function initDatabase()
    {
        $queries = self::createTableQueries($this->prefix);
        foreach ($queries as $q) {
            $this->db->query($q);
        }

        $tables = array('config');
        foreach ($tables as $t) {
            // make sure the tables are empty
            $this->db->query(
                sprintf(
                    "DELETE FROM %s",
                    $this->prefix.$t
                )
            );
        }
    }
}
