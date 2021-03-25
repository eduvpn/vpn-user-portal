<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\Config;
use LC\Portal\Exception\ConfigException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testSimpleConfig(): void
    {
        $c = new Config(
            [
                'foo' => 'bar',
            ]
        );
        $this->assertSame('bar', $c->requireString('foo'));
    }

    public function testNestedConfig(): void
    {
        $c = new Config(
            [
                'foo' => [
                    'bar' => 'baz',
                ],
            ]
        );
        $this->assertSame('baz', $c->s('foo')->requireString('bar'));
    }

    public function testNoParameters(): void
    {
        $configData = ['foo' => 'bar'];
        $c = new Config($configData);
        $this->assertSame($configData, $c->toArray());
    }

    public function testExists(): void
    {
        $c = new Config(['foo' => 'bar']);
        $this->assertNotNull($c->requireString('foo'));
        $this->assertNull($c->optionalString('bar'));
    }

    public function testMissingConfig(): void
    {
        try {
            $c = new Config([]);
            $c->requireString('foo');
            self::fail();
        } catch (ConfigException $e) {
            self::assertSame('key "foo" not available', $e->getMessage());
        }
    }

    public function testMissingNestedConfig(): void
    {
        try {
            $c = new Config(
                [
                    'foo' => [
                        'bar' => 'baz',
                    ],
                ]
            );
            $c->s('foo')->requireString('baz');
            self::fail();
        } catch (ConfigException $e) {
            self::assertSame('key "baz" not available', $e->getMessage());
        }
    }

    public function testFromFile(): void
    {
        $c = Config::fromFile(sprintf('%s/data/config.php', __DIR__));
        $this->assertSame('b', $c->s('bar')->requireString('a'));
    }

    public function testMyConfigDefaultValues(): void
    {
        $c = new MyConfig(['a' => ['b' => ['c' => 'd']]]);
        $this->assertSame(['baz'], $c->s('foo')->s('bar')->toArray());
        $this->assertSame(['b' => ['c' => 'd']], $c->requireArray('a'));
    }
}
