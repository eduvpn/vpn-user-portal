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
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->prefix = $prefix;
    }

    public function getConfigurations($userId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                "SELECT config_name, config_status FROM %s WHERE user_id = :user_id",
                $this->prefix.'configurations'
            )
        );
        $stmt->bindValue(":user_id", $userId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // FIXME: returns empty array when no configurations or still false?
        if (false !== $result) {
            return $result;
        }

        return null;
    }

    public function addConfiguration($userId, $configName)
    {
        $stmt = $this->db->prepare(
            sprintf(
                "INSERT INTO %s (user_id, config_name, config_status) VALUES(:user_id, :config_name, :config_status)",
                $this->prefix.'configurations'
            )
        );
        $stmt->bindValue(":user_id", $userId, PDO::PARAM_STR);
        $stmt->bindValue(":config_name", $configName, PDO::PARAM_STR);
        $stmt->bindValue(":config_status", "active", PDO::PARAM_STR);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new PdoStorageException("unable to add configuration");
        }
    }

    public function revokeConfiguration($userId, $configName)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'UPDATE %s SET config_status = "revoked" WHERE user_id = :user_id AND config_name = :config_name',
                $this->prefix.'configurations'
            )
        );
        $stmt->bindValue(":user_id", $userId, PDO::PARAM_STR);
        $stmt->bindValue(":config_name", $configName, PDO::PARAM_STR);
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
                config_name VARCHAR(255) NOT NULL,
                config_status VARCHAR(255) NOT NULL,
                UNIQUE (user_id, config_name)
            )",
            $prefix.'configurations'
        );

        return $query;
    }

    public function initDatabase()
    {
        $queries = self::createTableQueries($this->prefix);
        foreach ($queries as $q) {
            $this->db->query($q);
        }

        $tables = array('configurations');
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
