<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\Migration;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

class MigrationTest extends TestCase
{
    private string $schemaDir;
    private PDO $dbh;

    protected function setUp(): void
    {
        $this->schemaDir = sprintf('%s/schema', __DIR__);
        $this->dbh = new PDO('sqlite::memory:');
    }

    public function testInit(): void
    {
        $migration = new Migration($this->dbh, $this->schemaDir, '2018010101');
        $migration->init();
        $this->assertSame('2018010101', $migration->getCurrentVersion());
    }

    public function testSimpleMigration(): void
    {
        $migration = new Migration($this->dbh, $this->schemaDir, '2018010101');
        $migration->init();
        $this->assertSame('2018010101', $migration->getCurrentVersion());
        $this->dbh->exec('INSERT INTO foo (a) VALUES(3)');

        $migration = new Migration($this->dbh, $this->schemaDir, '2018010102');
        $this->assertTrue($migration->run());
        $this->assertSame('2018010102', $migration->getCurrentVersion());
        $this->assertFalse($migration->run());
        $sth = $this->dbh->query('SELECT * FROM foo');
        $this->assertSame(
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
        $migration = new Migration($this->dbh, $this->schemaDir, '2018010101');
        $migration->init();
        $this->assertSame('2018010101', $migration->getCurrentVersion());
        $this->dbh->exec('INSERT INTO foo (a) VALUES(3)');
        $migration = new Migration($this->dbh, $this->schemaDir, '2018010103');
        $migration->run();
        $this->assertSame('2018010103', $migration->getCurrentVersion());
        $sth = $this->dbh->query('SELECT * FROM foo');
        $this->assertSame(
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
        $migration = new Migration($this->dbh, $this->schemaDir, '2018010101');
        $this->assertSame('0000000000', $migration->getCurrentVersion());
        $migration->run();
        $this->assertSame('2018010101', $migration->getCurrentVersion());
        $sth = $this->dbh->query('SELECT * FROM foo');
        $this->assertSame(
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
        $migration = new Migration($this->dbh, $this->schemaDir, '2018020201');
        $migration->init();
        $this->assertSame('2018020201', $migration->getCurrentVersion());
        $migration = new Migration($this->dbh, $this->schemaDir, '2018020202');

        try {
            $migration->run();
            $this->fail();
        } catch (PDOException $e) {
            $this->assertSame('2018020201', $migration->getCurrentVersion());
        }
    }

    public function testWithForeignKeys(): void
    {
        $this->dbh->exec('PRAGMA foreign_keys = ON');
        $migration = new Migration($this->dbh, $this->schemaDir, '2018010101');
        $migration->init();
        $this->assertSame('2018010101', $migration->getCurrentVersion());
        $this->dbh->exec('INSERT INTO foo (a) VALUES(3)');

        $migration = new Migration($this->dbh, $this->schemaDir, '2018010102');
        $this->assertTrue($migration->run());
        $this->assertSame('2018010102', $migration->getCurrentVersion());
        $this->assertFalse($migration->run());
        $sth = $this->dbh->query('SELECT * FROM foo');
        $this->assertSame(
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
        $this->assertSame('1', $sth->fetchColumn(0));
        $sth->closeCursor();
    }
}
