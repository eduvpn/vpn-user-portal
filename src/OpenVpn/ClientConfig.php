<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\OpenVpn;

use DateTimeImmutable;
use Vpn\Portal\ClientConfigInterface;
use Vpn\Portal\OpenVpn\CA\CaInfo;
use Vpn\Portal\OpenVpn\CA\CertInfo;
use Vpn\Portal\OpenVpn\Exception\ClientConfigException;
use Vpn\Portal\ProfileConfig;

class ClientConfig implements ClientConfigInterface
{
    private int $nodeNumber;
    private ProfileConfig $profileConfig;
    private CaInfo $caInfo;
    private TlsCrypt $tlsCrypt;
    private CertInfo $certInfo;
    private bool $tcpOnly;
    private DateTimeImmutable $expiresAt;

    public function __construct(int $nodeNumber, ProfileConfig $profileConfig, CaInfo $caInfo, TlsCrypt $tlsCrypt, CertInfo $certInfo, bool $tcpOnly, DateTimeImmutable $expiresAt)
    {
        $this->nodeNumber = $nodeNumber;
        $this->profileConfig = $profileConfig;
        $this->caInfo = $caInfo;
        $this->tlsCrypt = $tlsCrypt;
        $this->certInfo = $certInfo;
        $this->tcpOnly = $tcpOnly;
        $this->expiresAt = $expiresAt;
    }

    public function contentType(): string
    {
        return 'application/x-openvpn-profile';
    }

    /**
     * XXX should this thing throw clientconfigexception? or the constructor?
     *
     * @throws \Vpn\Portal\OpenVpn\Exception\ClientConfigException
     */
    public function get(): string
    {
        $oUdpPortList = $this->profileConfig->oUdpPortList();
        $oTcpPortList = $this->profileConfig->oTcpPortList();
        if (0 !== \count($this->profileConfig->oExposedUdpPortList())) {
            $oUdpPortList = $this->profileConfig->oExposedUdpPortList();
        }
        if (0 !== \count($this->profileConfig->oExposedTcpPortList())) {
            $oTcpPortList = $this->profileConfig->oExposedTcpPortList();
        }

        $oUdpPortList = self::filterPortList($oUdpPortList, [53, 443]);
        $oTcpPortList = self::filterPortList($oTcpPortList, [80, 443]);
        // if "tcp_only" is set, and we do have some TCP ports, empty the list
        // of UDP ports, otherwise ignore "tcp_only"
        if ($this->tcpOnly && 0 !== \count($oTcpPortList)) {
            $oUdpPortList = [];
        }

        // make sure we have _something_ to connect to
        if (0 === \count($oUdpPortList) && 0 === \count($oTcpPortList)) {
            throw new ClientConfigException('no UDP/TCP port available');
        }

        $clientConfig = [
            sprintf('# Profile: %s (%s)', $this->profileConfig->displayName(), $this->profileConfig->profileId()),
            sprintf('# Expires: %s', $this->expiresAt->format(DateTimeImmutable::ATOM)),
            '',

            'dev tun',
            'client',
            'nobind',

            // the server can also push these if needed, and it should be up
            // to the client anyway if this is a good idea or not, e.g. running
            // in a chroot
            //'persist-key',
            //'persist-tun',
            'remote-cert-tls server',

            'verb 3',

            // wait this long (seconds) before trying the next server in the list
            'server-poll-timeout 10',

            // >= TLSv1.3
            'tls-version-min 1.3',

            // OpenVPN data channel encryption algorithm
            'data-ciphers AES-256-GCM:CHACHA20-POLY1305',

            // server dictates data channel key renegotiation interval
            'reneg-sec 0',

            '<ca>',
            $this->caInfo->pemCert(),
            '</ca>',

            '<cert>',
            $this->certInfo->pemCert(),
            '</cert>',

            '<key>',
            $this->certInfo->pemKey(),
            '</key>',

            '<tls-crypt>',
            $this->tlsCrypt->get($this->profileConfig->profileId()),
            '</tls-crypt>',
        ];

        // UDP
        foreach ($oUdpPortList as $udpPort) {
            $clientConfig[] = sprintf('remote %s %d udp', $this->profileConfig->hostName($this->nodeNumber), $udpPort);
        }

        // TCP
        foreach ($oTcpPortList as $tcpPort) {
            $clientConfig[] = sprintf('remote %s %d tcp', $this->profileConfig->hostName($this->nodeNumber), $tcpPort);
        }

        return implode(PHP_EOL, $clientConfig);
    }

    /**
     * Pick a random port from the available ports the client can connect to
     * that is not a "special" port. Also add a special port. This is a very
     * simple form of "load balancing" over different ports while always adding
     * a "special" port to increase the chances a client can connect on
     * "difficult" networks.
     *
     * @param array<int> $availablePortList
     * @param array<int> $specialPortList
     *
     * @return array<int>
     */
    private static function filterPortList(array $availablePortList, array $specialPortList): array
    {
        $clientPortList = [];

        // remove specialPortList entries from portList
        $normalAvailablePortList = array_diff($availablePortList, $specialPortList);
        $specialAvailablePortList = array_intersect($availablePortList, $specialPortList);

        // add a normal port (if available)
        if (0 !== \count($normalAvailablePortList)) {
            $clientPortList[] = $normalAvailablePortList[random_int(0, \count($normalAvailablePortList) - 1)];
        }

        // add a special port (if available)
        if (0 !== \count($specialAvailablePortList)) {
            $clientPortList[] = $specialAvailablePortList[random_int(0, \count($specialAvailablePortList) - 1)];
        }

        return $clientPortList;
    }
}
