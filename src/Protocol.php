<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\Exception\ProtocolException;

/**
 * Determine which VPN protocol to use based on the client/user preferences
 * and profile profile support.
 */
class Protocol
{
    /**
     * @param array{wireguard:bool,openvpn:bool} $clientProtoSupport
     */
    public static function determine(ProfileConfig $profileConfig, array $clientProtoSupport, ?string $publicKey, bool $preferTcp): string
    {
        $wSupport = $clientProtoSupport['wireguard'];
        $oSupport = $clientProtoSupport['openvpn'];

        if (false === $oSupport && false === $wSupport) {
            throw new ProtocolException('neither wireguard, nor openvpn supported by client');
        }

        if ($oSupport && false === $wSupport) {
            if ($profileConfig->oSupport()) {
                return 'openvpn';
            }

            throw new ProtocolException('profile does not support openvpn, but only openvpn is acceptable for client');
        }

        if ($wSupport && false === $oSupport) {
            if ($profileConfig->wSupport()) {
                return 'wireguard';
            }

            throw new ProtocolException('profile does not support wireguard, but only wireguard is acceptable for client');
        }

        // At this point, the client does not *explicitly* specify their
        // supported protocols, so we assume both are supported...

        // Profile only supports OpenVPN
        if ($profileConfig->oSupport() && !$profileConfig->wSupport()) {
            return 'openvpn';
        }

        // Profile only supports WireGuard
        if (!$profileConfig->oSupport() && $profileConfig->wSupport()) {
            return 'wireguard';
        }

        // Profile supports OpenVPN & WireGuard

        // VPN client prefers connecting over TCP
        if ($preferTcp) {
            // but this has only meaning if there are actually TCP ports to
            // connect to...
            if (0 !== \count($profileConfig->oExposedTcpPortList()) || 0 !== \count($profileConfig->oTcpPortList())) {
                return 'openvpn';
            }
        }

        // Profile prefers OpenVPN
        if ('openvpn' === $profileConfig->preferredProto()) {
            return 'openvpn';
        }

        // VPN client provides a WireGuard Public Key, server prefers WireGuard
        if (null !== $publicKey) {
            return 'wireguard';
        }

        // Server prefers WireGuard, but VPN client does not provide a
        // WireGuard Public Key, so use OpenVPN...
        return 'openvpn';
    }
}
