<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\Cfg\ProfileConfig;
use Vpn\Portal\Exception\ProtocolException;

/**
 * Determine which VPN protocol to use based on the client/user preferences
 * and profile profile support.
 */
class Protocol
{
    /**
     * @param array{wireguard:bool,openvpn:bool} $clientProtoSupport
     *
     * @return array<string>
     */
    public static function determine(ProfileConfig $profileConfig, array $clientProtoSupport, ?string $publicKey, bool $preferTcp): array
    {
        // figure out common VPN protocols between client and profile
        $commonProtoList = [];
        if ($clientProtoSupport['wireguard'] && null !== $publicKey && $profileConfig->wSupport()) {
            $commonProtoList[] = 'wireguard';
        }
        if ($clientProtoSupport['openvpn'] && $profileConfig->oSupport()) {
            $commonProtoList[] = 'openvpn';
        }
        if (0 === count($commonProtoList)) {
            throw new ProtocolException('no common VPN protocol support between client and server profile');
        }
        if (1 === count($commonProtoList)) {
            // only one protocol in common, use it
            return $commonProtoList;
        }

        // both WireGuard and OpenVPN supported by client and profile...

        if ($preferTcp) {
            // VPN client prefers connecting over TCP, make sure OpenVPN has
            // some TCP ports available, if it does, prefer OpenVPN
            if (0 !== \count($profileConfig->oExposedTcpPortList()) || 0 !== \count($profileConfig->oTcpPortList())) {
                return ['openvpn', 'wireguard'];
            }
        }

        if ('openvpn' === $profileConfig->preferredProto()) {
            return ['openvpn', 'wireguard'];
        }

        // profile prefers WireGuard
        return ['wireguard', 'openvpn'];
    }

    /**
     * We only take the Accept header serious if we detect at least one
     * mime-type we recognize, otherwise we assume it is garbage and consider
     * it as "not sent".
     *
     * @return array{wireguard:bool,openvpn:bool}
     */
    public static function parseMimeType(?string $httpAccept): array
    {
        if (null === $httpAccept) {
            return ['wireguard' => true, 'openvpn' => true];
        }

        $oSupport = false;
        $wSupport = false;
        $takeSerious = false;

        $mimeTypeList = explode(',', $httpAccept);
        foreach ($mimeTypeList as $mimeType) {
            $mimeType = trim($mimeType);
            if ('application/x-openvpn-profile' === $mimeType) {
                $oSupport = true;
                $takeSerious = true;
            }
            if ('application/x-wireguard-profile' === $mimeType) {
                $wSupport = true;
                $takeSerious = true;
            }
        }
        if (false === $takeSerious) {
            return ['wireguard' => true, 'openvpn' => true];
        }

        return ['wireguard' => $wSupport, 'openvpn' => $oSupport];
    }
}
