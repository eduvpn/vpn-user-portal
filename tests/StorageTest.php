<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\Tests;

use DateInterval;
use DateTime;
use LetsConnect\Portal\Storage;
use PDO;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    /** @var Storage */
    private $storage;

    public function setUp()
    {
        $dateTime = new DateTime('2018-01-01 13:37:00');
        $db = new PDO('sqlite::memory:');
        $this->storage = new Storage($db, \dirname(__DIR__).'/schema', new DateInterval('P90D'));
        $this->storage->setDateTime($dateTime);
        $this->storage->init();
    }

    public function testValid()
    {
        $this->storage->add('foo', 'bar');
        $this->assertInstanceOf('\LetsConnect\Common\Http\UserInfo', $this->storage->isValid('foo', 'bar'));
    }

    public function testInvalidPass()
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->isValid('foo', 'baz'));
    }

    public function testInvalidUser()
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->isValid('fop', 'bar'));
    }

    public function testInvalidUserPass()
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->isValid('fop', 'baz'));
    }

    public function testUpdatePasswordExistingUser()
    {
        $this->storage->add('foo', 'bar');
        $this->assertTrue($this->storage->updatePassword('foo', 'baz'));
        $this->assertInstanceOf('\LetsConnect\Common\Http\UserInfo', $this->storage->isValid('foo', 'baz'));
    }

    public function testUserExists()
    {
        $this->storage->add('foo', 'bar');
        $this->assertTrue($this->storage->userExists('foo'));
    }

    public function testUserNotExists()
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->userExists('bar'));
    }

    public function testUpdatePasswordNonExistingUser()
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->updatePassword('bar', 'baz'));
        $this->assertInstanceOf('\LetsConnect\Common\Http\UserInfo', $this->storage->isValid('foo', 'bar'));
    }

    public function testNonExpiredHasAuthorization()
    {
        $this->assertFalse($this->storage->hasAuthorization('random_1'));

        // non-expired authorization
        $this->storage->storeAuthorization('foo', 'client_id', 'scope', 'random_1', new DateTime('2018-01-01'));
        $this->assertTrue($this->storage->hasAuthorization('random_1'));
    }

    public function testExpiredHasAuthorization()
    {
        $this->assertFalse($this->storage->hasAuthorization('random_1'));

        // expired authorization
        $this->storage->storeAuthorization('foo', 'client_id', 'scope', 'random_1', new DateTime('2017-01-01'));
        $this->assertFalse($this->storage->hasAuthorization('random_1'));
        $this->assertSame([], $this->storage->getAuthorizations('foo'));
    }
}
