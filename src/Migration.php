<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
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
    /**
     * Run the migration.
     */
    public static function run(PDO $db, string $schemaDir, string $schemaVersion, bool $autoInitMigrate): void
    {
        $currentVersion = self::getCurrentVersion($db);
        if ($schemaVersion === $currentVersion) {
            // up to date
            return;
        }

        if (!$autoInitMigrate) {
            throw new MigrationException('manual database initialization or migration required');
        }

        if (null === $currentVersion) {
            // no tables yet, initialize database
            self::runQueries($db, self::getQueriesFromFile(self::getNameForDriver($db, sprintf('%s/%s.schema', $schemaDir, $schemaVersion))));
            self::createVersionTable($db, $schemaVersion);

            return;
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
                        $db->beginTransaction();
                        $db->exec(sprintf("DELETE FROM version WHERE current_version = '%s'", $fromVersion));
                        self::runQueries($db, $queryList);
                        $db->exec(sprintf("INSERT INTO version (current_version) VALUES('%s')", $toVersion));
                        $db->commit();
                        $currentVersion = $toVersion;
                    } catch (PDOException $e) {
                        // something went wrong with the SQL queries
                        $db->rollback();

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
    private static function getCurrentVersion(PDO $db): ?string
    {
        try {
            $sth = $db->query('SELECT current_version FROM version');
            $currentVersion = $sth->fetchColumn();
            if (!\is_string($currentVersion)) {
                // XXX this means database corruption?
                throw new MigrationException('unable to retrieve current version');
            }
            // XXX validate?

            return $currentVersion;
        } catch (PDOException $e) {
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
        $db->exec('CREATE TABLE version (current_version TEXT NOT NULL)');
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
            if (0 === Binary::safeStrlen(trim($dbQuery))) {
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
        /** @var false|string $fileContent */
        $fileContent = file_get_contents($filePath);
        if (false === $fileContent) {
            throw new RuntimeException(sprintf('unable to read "%s"', $filePath));
        }

        return explode(';', $fileContent);
    }

    private static function validateSchemaVersion(string $schemaVersion): string
    {
        if (1 !== preg_match('/^[0-9]{10}$/', $schemaVersion)) {
            throw new RangeException('schemaVersion must be 10 a digit string');
        }

        return $schemaVersion;
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
}
