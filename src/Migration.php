<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Exception;
use PDO;
use PDOException;
use RangeException;
use RuntimeException;
use Vpn\Portal\Exception\MigrationException;

class Migration
{
    public static function run(PDO $db, string $schemaDir, string $schemaVersion, bool $autoInit, bool $autoMigrate): void
    {
        if (PDO::ERRMODE_EXCEPTION !== $db->getAttribute(PDO::ATTR_ERRMODE)) {
            throw new MigrationException('PDO::ATTR_ERRMODE MUST be PDO::ERRMODE_EXCEPTION');
        }

        $currentVersion = self::getCurrentVersion($db);
        if ($schemaVersion === $currentVersion) {
            // all good, initialized and up to date
            return;
        }

        if (null === $currentVersion) {
            // not yet initialized
            if (!$autoInit) {
                throw new MigrationException('database not initialized');
            }

            self::runQueries($db, self::getQueriesFromFile(self::getNameForDriver($db, sprintf('%s/%s.schema', $schemaDir, $schemaVersion))));
            self::createVersionTable($db, $schemaVersion);

            return;
        }

        // migration required
        if (!$autoMigrate) {
            throw new MigrationException('database migration required');
        }

        /** @var array<string>|false $migrationList */
        $migrationList = glob(self::getNameForDriver($db, sprintf('%s/*_*.migration', $schemaDir)));
        if (false === $migrationList) {
            throw new RuntimeException(sprintf('unable to read schema directory "%s"', $schemaDir));
        }

        self::lock($db);

        try {
            foreach ($migrationList as $migrationFile) {
                $migrationVersion = self::getNameForDriver($db, basename($migrationFile, '.migration'));
                [$fromVersion, $toVersion] = self::validateMigrationVersion($migrationVersion);
                if ($fromVersion === $currentVersion && $fromVersion !== $schemaVersion) {
                    // get the queries before we start the transaction as we
                    // ONLY want to deal with "PDOExceptions" once the
                    // transacation started...
                    $queryList = self::getQueriesFromFile(self::getNameForDriver($db, sprintf('%s/%s.migration', $schemaDir, $migrationVersion)));

                    try {
                        self::dbBeginTransaction($db);
                        $db->exec(sprintf("DELETE FROM version WHERE current_version = '%s'", $fromVersion));
                        self::runQueries($db, $queryList);
                        $db->exec(sprintf("INSERT INTO version (current_version) VALUES('%s')", $toVersion));
                        self::dbCommit($db);
                        $currentVersion = $toVersion;
                    } catch (PDOException $e) {
                        self::dbRollback($db);

                        throw $e;
                    }
                }
            }
        } catch (Exception $e) {
            // something went wrong that was not related to SQL queries
            self::unlock($db);

            throw $e;
        }

        self::unlock($db);

        $currentVersion = self::getCurrentVersion($db);
        if ($currentVersion !== $schemaVersion) {
            throw new MigrationException(sprintf('unable to migrate to database schema version "%s", not all required migrations are available', $schemaVersion));
        }
    }

    /**
     * Gets the current version of the database schema.
     */
    public static function getCurrentVersion(PDO $db): ?string
    {
        try {
            $sth = $db->query('SELECT current_version FROM version');
            $currentVersion = (string) $sth->fetchColumn();
            self::validateSchemaVersion($currentVersion);

            return $currentVersion;
        } catch (PDOException $e) {
            // the "version" table probably does not exist (yet)
            return null;
        }
    }

    /**
     * See if there is a file available specifically for this DB driver. If
     * so, use it, if not fallback to the "default".
     */
    private static function getNameForDriver(PDO $db, string $fileName): string
    {
        $driverName = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (file_exists($fileName.'.'.$driverName)) {
            return $fileName.'.'.$driverName;
        }

        return $fileName;
    }

    private static function lock(PDO $db): void
    {
        // this creates a "lock" as only one process will succeed in this...
        $db->exec('CREATE TABLE _migration_in_progress (dummy INTEGER)');

        if ('sqlite' === $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $db->exec('PRAGMA foreign_keys = OFF');
        }
    }

    private static function unlock(PDO $db): void
    {
        if ('sqlite' === $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $db->exec('PRAGMA foreign_keys = ON');
        }

        // release "lock"
        $db->exec('DROP TABLE _migration_in_progress');
    }

    private static function createVersionTable(PDO $db, string $schemaVersion): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS version (current_version TEXT NOT NULL)');
        // we know that schemaVersion is a 10 digit string as per
        // validateSchemaVersion
        $db->exec(sprintf("INSERT INTO version (current_version) VALUES('%s')", $schemaVersion));
    }

    /**
     * @param array<string> $queryList
     */
    private static function runQueries(PDO $db, array $queryList): void
    {
        foreach ($queryList as $dbQuery) {
            if (0 === strlen(trim($dbQuery))) {
                // ignore empty line(s)
                continue;
            }
            $db->exec($dbQuery);
        }
    }

    /**
     * @return array<string>
     */
    private static function getQueriesFromFile(string $filePath): array
    {
        if (false === $fileContent = file_get_contents($filePath)) {
            throw new RuntimeException(sprintf('unable to read "%s"', $filePath));
        }

        return explode(';', $fileContent);
    }

    private static function validateSchemaVersion(string $schemaVersion): void
    {
        if (1 !== preg_match('/^[0-9]{10}$/', $schemaVersion)) {
            throw new RangeException('schemaVersion must be 10 a digit string');
        }
    }

    /**
     * @return array<string>
     */
    private static function validateMigrationVersion(string $migrationVersion): array
    {
        if (1 !== preg_match('/^[0-9]{10}_[0-9]{10}$/', $migrationVersion)) {
            throw new RangeException('migrationVersion must be two times a 10 digit string separated by an underscore');
        }

        return explode('_', $migrationVersion, 2);
    }

    private static function dbBeginTransaction(PDO $db): void
    {
        // MySQL does not allow for CREATE/DROP TABLE inside a transaction, it
        // will commit by itself.
        // @see https://mariadb.com/kb/en/sql-statements-that-cause-an-implicit-commit/
        if ('mysql' !== $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $db->beginTransaction();
        }
    }

    private static function dbCommit(PDO $db): void
    {
        // MySQL does not allow for CREATE/DROP TABLE inside a transaction, it
        // will commit by itself.
        // @see https://mariadb.com/kb/en/sql-statements-that-cause-an-implicit-commit/
        if ('mysql' !== $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $db->commit();
        }
    }

    private static function dbRollback(PDO $db): void
    {
        // MySQL does not allow for CREATE/DROP TABLE inside a transaction, it
        // will commit by itself.
        // @see https://mariadb.com/kb/en/sql-statements-that-cause-an-implicit-commit/
        if ('mysql' !== $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $db->rollback();
        }
    }
}
