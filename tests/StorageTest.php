<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use DateInterval;
use DateTime;
use LC\Portal\Storage;
use PDO;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    /** @var Storage */
    private $storage;

    protected function setUp(): void
    {
        $dateTime = new DateTime('2018-01-01 13:37:00');
        $db = new PDO('sqlite::memory:');
        $this->storage = new Storage($db, \dirname(__DIR__).'/schema', new DateInterval('P90D'));
        $this->storage->setDateTime($dateTime);
        $this->storage->init();
    }

    public function testValid(): void
    {
        $this->storage->add('foo', 'bar');
        $this->assertInstanceOf('\LC\Portal\Http\UserInfo', $this->storage->isValid('foo', 'bar'));
    }

    public function testInvalidPass(): void
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->isValid('foo', 'baz'));
    }

    public function testInvalidUser(): void
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->isValid('fop', 'bar'));
    }

    public function testInvalidUserPass(): void
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->isValid('fop', 'baz'));
    }

    public function testUpdatePasswordExistingUser(): void
    {
        $this->storage->add('foo', 'bar');
        $this->assertTrue($this->storage->updatePassword('foo', 'baz'));
        $this->assertInstanceOf('\LC\Portal\Http\UserInfo', $this->storage->isValid('foo', 'baz'));
    }

    public function testUserExists(): void
    {
        $this->storage->add('foo', 'bar');
        $this->assertTrue($this->storage->userExists('foo'));
    }

    public function testUserNotExists(): void
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->userExists('bar'));
    }

    public function testUpdatePasswordNonExistingUser(): void
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->updatePassword('bar', 'baz'));
        $this->assertInstanceOf('\LC\Portal\Http\UserInfo', $this->storage->isValid('foo', 'bar'));
    }

    public function testHasAuthorization(): void
    {
        $this->assertFalse($this->storage->hasAuthorization('random_1'));
        $this->storage->storeAuthorization('foo', 'client_id', 'scope', 'random_1');
        $this->assertTrue($this->storage->hasAuthorization('random_1'));
    }
}
