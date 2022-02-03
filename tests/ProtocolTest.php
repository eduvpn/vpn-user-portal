<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\Protocol;

/**
 * @internal
 * @coversNothing
 */
final class ProtocolTest extends TestCase
{
    public const PROTO_BOTH = ['wireguard' => true, 'openvpn' => true];
    public const PROTO_OPENVPN_ONLY = ['wireguard' => false, 'openvpn' => true];
    public const PROTO_WIREGUARD_ONLY = ['wireguard' => true, 'openvpn' => false];
    public const PROTO_NONE = ['wireguard' => false, 'openvpn' => false];

    public function testParseMimeType(): void
    {
        static::assertSame(self::PROTO_BOTH, Protocol::parseMimeType(null));
        static::assertSame(self::PROTO_BOTH, Protocol::parseMimeType('foo'));
        static::assertSame(self::PROTO_OPENVPN_ONLY, Protocol::parseMimeType('application/x-openvpn-profile'));
        static::assertSame(self::PROTO_OPENVPN_ONLY, Protocol::parseMimeType('application/x-openvpn-profile, application/x-openvpn-profile'));
        static::assertSame(self::PROTO_WIREGUARD_ONLY, Protocol::parseMimeType('application/x-wireguard-profile'));
        static::assertSame(self::PROTO_WIREGUARD_ONLY, Protocol::parseMimeType('application/x-wireguard-profile, foo/bar'));
        static::assertSame(self::PROTO_BOTH, Protocol::parseMimeType('application/x-wireguard-profile, application/x-openvpn-profile'));
    }
}
