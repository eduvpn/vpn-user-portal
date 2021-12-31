<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Vpn\Portal\Exception\MigrationException;
use Vpn\Portal\Migration;

/**
 * @internal
 * @coversNothing
 */
final class MigrationTest extends TestCase
{
    private string $schemaDir;
    private PDO $dbh;

    protected function setUp(): void
    {
        $this->schemaDir = sprintf('%s/schema', __DIR__);
        $this->dbh = new PDO('sqlite::memory:');
        // on older versions of PHP we need to set the ERRMODE explicitly
        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // in PHP < 8.1 the ATTR_STRINGIFY_FETCHES attribute was always true,
        // but changed to a default of false in 8.1. Setting this option
        // restores the pre-8.1 behavior. We may switch it to false at some
        // point, but this requires proper testing...
        // @see https://www.php.net/manual/en/migration81.incompatible.php
        $this->dbh->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
    }

    public function testInit(): void
    {
        Migration::run($this->dbh, $this->schemaDir, '2018010101', true, true);
        static::assertSame('2018010101', Migration::getCurrentVersion($this->dbh));
    }

    public function testInitNotAllowed(): void
    {
        static::expectException(MigrationException::class);
        static::expectExceptionMessage('database not initialized');
        Migration::run($this->dbh, $this->schemaDir, '2018010101', false, false);
    }

    public function testMigrationNotAllowed(): void
    {
        static::expectException(MigrationException::class);
        static::expectExceptionMessage('database migration required');
        Migration::run($this->dbh, $this->schemaDir, '2018010101', true, false);
        static::assertSame('2018010101', Migration::getCurrentVersion($this->dbh));
        $this->dbh->exec('INSERT INTO foo (a) VALUES(3)');
        Migration::run($this->dbh, $this->schemaDir, '2018010102', false, false);
    }

    public function testSimpleMigration(): void
    {
        Migration::run($this->dbh, $this->schemaDir, '2018010101', true, true);
        static::assertSame('2018010101', Migration::getCurrentVersion($this->dbh));
        $this->dbh->exec('INSERT INTO foo (a) VALUES(3)');

        Migration::run($this->dbh, $this->schemaDir, '2018010102', true, true);
        static::assertSame('2018010102', Migration::getCurrentVersion($this->dbh));
        $sth = $this->dbh->query('SELECT * FROM foo');
        static::assertSame(
            [
                [
                    'a' => '3',
                    'b' => '0',
                ],
            ],
            $sth->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function testMultiMigration(): void
    {
        Migration::run($this->dbh, $this->schemaDir, '2018010101', true, true);
        static::assertSame('2018010101', Migration::getCurrentVersion($this->dbh));
        $this->dbh->exec('INSERT INTO foo (a) VALUES(3)');
        Migration::run($this->dbh, $this->schemaDir, '2018010103', true, true);
        static::assertSame('2018010103', Migration::getCurrentVersion($this->dbh));
        $sth = $this->dbh->query('SELECT * FROM foo');
        static::assertSame(
            [
                [
                    'a' => '3',
                    'b' => '0',
                    'c' => null,
                ],
            ],
            $sth->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function testNoVersion(): void
    {
        // we have a database without versioning, but we want to bring it
        // under version control, we can't run init as that would install the
        // version table...
        $this->dbh->exec('CREATE TABLE foo (a INTEGER NOT NULL)');
        $this->dbh->exec('INSERT INTO foo (a) VALUES(3)');
        static::assertNull(Migration::getCurrentVersion($this->dbh));
        Migration::run($this->dbh, $this->schemaDir, '2018010101', true, true);
        static::assertSame('2018010101', Migration::getCurrentVersion($this->dbh));
        $sth = $this->dbh->query('SELECT * FROM foo');
        static::assertSame(
            [
                [
                    'a' => '3',
                ],
            ],
            $sth->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function testFailingUpdate(): void
    {
        Migration::run($this->dbh, $this->schemaDir, '2018020201', true, true);
        static::assertSame('2018020201', Migration::getCurrentVersion($this->dbh));

        try {
            Migration::run($this->dbh, $this->schemaDir, '2018020202', true, true);
            static::fail();
        } catch (PDOException $e) {
            static::assertSame('2018020201', Migration::getCurrentVersion($this->dbh));
        }
    }

    public function testWithForeignKeys(): void
    {
        $this->dbh->exec('PRAGMA foreign_keys = ON');
        Migration::run($this->dbh, $this->schemaDir, '2018010101', true, true);
        static::assertSame('2018010101', Migration::getCurrentVersion($this->dbh));
        $this->dbh->exec('INSERT INTO foo (a) VALUES(3)');

        Migration::run($this->dbh, $this->schemaDir, '2018010102', true, true);
        static::assertSame('2018010102', Migration::getCurrentVersion($this->dbh));
        $sth = $this->dbh->query('SELECT * FROM foo');
        static::assertSame(
            [
                [
                    'a' => '3',
                    'b' => '0',
                ],
            ],
            $sth->fetchAll(PDO::FETCH_ASSOC)
        );
        // make sure FK are back on again
        $sth = $this->dbh->query('PRAGMA foreign_keys');
        static::assertSame('1', $sth->fetchColumn(0));
        $sth->closeCursor();
    }
}
