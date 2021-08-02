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

/**
 * @internal
 * @coversNothing
 */
final class ConfigTest extends TestCase
{
    public function testSimpleConfig(): void
    {
        $c = new Config(
            [
                'foo' => 'bar',
            ]
        );
        static::assertSame('bar', $c->requireString('foo'));
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
        static::assertSame('baz', $c->s('foo')->requireString('bar'));
    }

    public function testNoParameters(): void
    {
        $configData = ['foo' => 'bar'];
        $c = new Config($configData);
        static::assertSame($configData, $c->toArray());
    }

    public function testExists(): void
    {
        $c = new Config(['foo' => 'bar']);
        static::assertNotNull($c->requireString('foo'));
        static::assertNull($c->optionalString('bar'));
    }

    public function testMissingConfig(): void
    {
        try {
            $c = new Config([]);
            $c->requireString('foo');
            static::fail();
        } catch (ConfigException $e) {
            static::assertSame('key "foo" not available', $e->getMessage());
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
            static::fail();
        } catch (ConfigException $e) {
            static::assertSame('key "baz" not available', $e->getMessage());
        }
    }

    public function testFromFile(): void
    {
        $c = Config::fromFile(sprintf('%s/data/config.php', __DIR__));
        static::assertSame('b', $c->s('bar')->requireString('a'));
    }

    public function testMyConfigDefaultValues(): void
    {
        $c = new MyConfig(['a' => ['b' => ['c' => 'd']]]);
        static::assertSame(['baz'], $c->s('foo')->s('bar')->toArray());
        static::assertSame(['b' => ['c' => 'd']], $c->requireArray('a'));
    }
}
