<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\Tests;

use DateTime;
use LetsConnect\Portal\Storage;
use PDO;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    /** @var Storage */
    private $storage;

    /**
     * @return void
     */
    public function setUp()
    {
        $dateTime = new DateTime('2018-01-01 13:37:00');
        $db = new PDO('sqlite::memory:');
        $this->storage = new Storage($db, \dirname(__DIR__).'/schema');
        $this->storage->setDateTime($dateTime);
        $this->storage->init();
    }

    /**
     * @return void
     */
    public function testValid()
    {
        $this->storage->add('foo', 'bar');
        $this->assertInstanceOf('\LetsConnect\Common\Http\UserInfo', $this->storage->isValid('foo', 'bar'));
    }

    /**
     * @return void
     */
    public function testInvalidPass()
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->isValid('foo', 'baz'));
    }

    /**
     * @return void
     */
    public function testInvalidUser()
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->isValid('fop', 'bar'));
    }

    /**
     * @return void
     */
    public function testInvalidUserPass()
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->isValid('fop', 'baz'));
    }

    /**
     * @return void
     */
    public function testUpdatePasswordExistingUser()
    {
        $this->storage->add('foo', 'bar');
        $this->assertTrue($this->storage->updatePassword('foo', 'baz'));
        $this->assertInstanceOf('\LetsConnect\Common\Http\UserInfo', $this->storage->isValid('foo', 'baz'));
    }

    /**
     * @return void
     */
    public function testUserExists()
    {
        $this->storage->add('foo', 'bar');
        $this->assertTrue($this->storage->userExists('foo'));
    }

    /**
     * @return void
     */
    public function testUserNotExists()
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->userExists('bar'));
    }

    /**
     * @return void
     */
    public function testUpdatePasswordNonExistingUser()
    {
        $this->storage->add('foo', 'bar');
        $this->assertFalse($this->storage->updatePassword('bar', 'baz'));
        $this->assertInstanceOf('\LetsConnect\Common\Http\UserInfo', $this->storage->isValid('foo', 'bar'));
    }

    /**
     * @return void
     */
    public function testHasAuthorization()
    {
        $this->assertFalse($this->storage->hasAuthorization('random_1'));
        $this->storage->storeAuthorization('foo', 'client_id', 'scope', 'random_1');
        $this->assertTrue($this->storage->hasAuthorization('random_1'));
    }
}
