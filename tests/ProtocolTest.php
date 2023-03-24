<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\Exception\ProtocolException;
use Vpn\Portal\Protocol;

/**
 * @internal
 *
 * @coversNothing
 */
final class ProtocolTest extends TestCase
{
    private const PROTO_BOTH = ['wireguard' => true, 'openvpn' => true];
    private const PROTO_OPENVPN_ONLY = ['wireguard' => false, 'openvpn' => true];
    private const PROTO_WIREGUARD_ONLY = ['wireguard' => true, 'openvpn' => false];
    private const PROTO_NONE = ['wireguard' => false, 'openvpn' => false];
    private const PUBLIC_KEY = 'vb+cQ2qTSwNjcnht2cURSubD/NC8CsD0/QGygtrb5Es=';

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

    public function testDetermineHappy(): void
    {
        // Client Supports OpenVPN & WireGuard
        static::assertSame(['openvpn', 'wireguard'], Protocol::determine(self::getConfig()->profileConfig('prefer-openvpn'), self::PROTO_BOTH, self::PUBLIC_KEY, false));
        static::assertSame(['wireguard', 'openvpn'], Protocol::determine(self::getConfig()->profileConfig('prefer-wireguard'), self::PROTO_BOTH, self::PUBLIC_KEY, false));
        static::assertSame(['wireguard'], Protocol::determine(self::getConfig()->profileConfig('wireguard-only'), self::PROTO_BOTH, self::PUBLIC_KEY, false));
        static::assertSame(['openvpn'], Protocol::determine(self::getConfig()->profileConfig('openvpn-only'), self::PROTO_BOTH, self::PUBLIC_KEY, false));
        // test "preferTcp" on profile that supports both OpenVPN & WireGuard, but prefers WireGuard
        static::assertSame(['openvpn', 'wireguard'], Protocol::determine(self::getConfig()->profileConfig('prefer-wireguard'), self::PROTO_BOTH, self::PUBLIC_KEY, true));

        // Client Supports WireGuard
        static::assertSame(['wireguard'], Protocol::determine(self::getConfig()->profileConfig('prefer-openvpn'), self::PROTO_WIREGUARD_ONLY, self::PUBLIC_KEY, false));
        static::assertSame(['wireguard'], Protocol::determine(self::getConfig()->profileConfig('prefer-wireguard'), self::PROTO_WIREGUARD_ONLY, self::PUBLIC_KEY, false));
        static::assertSame(['wireguard'], Protocol::determine(self::getConfig()->profileConfig('wireguard-only'), self::PROTO_WIREGUARD_ONLY, self::PUBLIC_KEY, false));

        // Client Supports OpenVPN
        static::assertSame(['openvpn'], Protocol::determine(self::getConfig()->profileConfig('prefer-openvpn'), self::PROTO_OPENVPN_ONLY, null, false));
        static::assertSame(['openvpn'], Protocol::determine(self::getConfig()->profileConfig('prefer-wireguard'), self::PROTO_OPENVPN_ONLY, null, false));
        static::assertSame(['openvpn'], Protocol::determine(self::getConfig()->profileConfig('openvpn-only'), self::PROTO_OPENVPN_ONLY, null, false));
    }

    public function testDetermineClientSupportsNoProtocol(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('no common VPN protocol support between client and server profile');
        Protocol::determine(self::getConfig()->profileConfig('prefer-openvpn'), self::PROTO_NONE, self::PUBLIC_KEY, false);
    }

    public function testDetermineWireGuardOnlyClientOpenVpnOnlyProfile(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('no common VPN protocol support between client and server profile');
        Protocol::determine(self::getConfig()->profileConfig('openvpn-only'), self::PROTO_WIREGUARD_ONLY, self::PUBLIC_KEY, false);
    }

    public function testDetermineOpenVpnOnlyClientWireGuardOnlyProfile(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('no common VPN protocol support between client and server profile');
        Protocol::determine(self::getConfig()->profileConfig('wireguard-only'), self::PROTO_OPENVPN_ONLY, null, false);
    }

    public function testDetermineWireGuardOnlyWithoutPublicKey(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('no common VPN protocol support between client and server profile');
        Protocol::determine(self::getConfig()->profileConfig('wireguard-only'), self::PROTO_WIREGUARD_ONLY, null, false);
    }

    public static function getConfig(): Config
    {
        return new Config(
            [
                'Db' => [
                    'dbDsn' => 'sqlite::memory:',
                ],
                'ProfileList' => [
                    [
                        'profileId' => 'prefer-openvpn',
                        'displayName' => 'Both (Prefer OpenVPN)',
                        'preferredProto' => 'openvpn',
                        'hostName' => 'vpn.example.org',
                        'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                        'wRangeFour' => '10.20.27.0/24',
                        'wRangeSix' => 'fd04:cabc:702c:55d9::/64',
                        'oRangeFour' => '10.222.172.0/24',
                        'oRangeSix' => 'fde6:76bd:ad97:ac5e::/64',
                        'oUdpPortList' => [1194],
                        'oTcpPortList' => [1194],
                    ],
                    [
                        'profileId' => 'prefer-wireguard',
                        'displayName' => 'Both (Prefer WireGuard)',
                        'preferredProto' => 'wireguard',
                        'hostName' => 'vpn.example.org',
                        'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                        'wRangeFour' => '10.89.165.0/24',
                        'wRangeSix' => 'fcda:8264:a469:e3c::/64',
                        'oRangeFour' => '10.51.216.0/24',
                        'oRangeSix' => 'fd66:3d54:4a82:77b3::/64',
                        'oUdpPortList' => [1195],
                        'oTcpPortList' => [1195],
                    ],
                    [
                        'profileId' => 'wireguard-only',
                        'displayName' => 'WireGuard Only',
                        'hostName' => 'vpn.example.org',
                        'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                        'wRangeFour' => '10.45.198.0/24',
                        'wRangeSix' => 'fcb5:f467:a7bc:8c90::/64',
                    ],
                    [
                        'profileId' => 'openvpn-only',
                        'displayName' => 'OpenVPN Only',
                        'hostName' => 'vpn.example.org',
                        'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                        'oRangeFour' => '172.18.135.0/24',
                        'oRangeSix' => 'fdc7:22b6:5572:6089::/64',
                        'oUdpPortList' => [1196],
                        'oTcpPortList' => [1196],
                    ],
                ],
            ]
        );
    }
}
