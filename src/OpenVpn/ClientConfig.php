<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OpenVpn;

use LC\Portal\Binary;
use LC\Portal\ClientConfigInterface;
use LC\Portal\OpenVpn\CA\CaInfo;
use LC\Portal\OpenVpn\CA\CertInfo;
use LC\Portal\OpenVpn\Exception\ClientConfigException;
use LC\Portal\ProfileConfig;

class ClientConfig implements ClientConfigInterface
{
    public const STRATEGY_FIRST = 0;
    public const STRATEGY_RANDOM = 1;
    public const STRATEGY_ALL = 2;

    private ProfileConfig $profileConfig;
    private CaInfo $caInfo;
    private TlsCrypt $tlsCrypt;
    private CertInfo $certInfo;
    private int $remoteStrategy;
    private bool $tcpOnly;

    public function __construct(ProfileConfig $profileConfig, CaInfo $caInfo, TlsCrypt $tlsCrypt, CertInfo $certInfo, int $remoteStrategy, bool $tcpOnly)
    {
        $this->profileConfig = $profileConfig;
        $this->caInfo = $caInfo;
        $this->tlsCrypt = $tlsCrypt;
        $this->certInfo = $certInfo;
        $this->remoteStrategy = $remoteStrategy;
        $this->tcpOnly = $tcpOnly;
    }

    public function contentType(): string
    {
        return 'application/x-openvpn-profile';
    }

    public function get(): string
    {
        // make a list of ports/proto to add to the configuration file
        $hostName = $this->profileConfig->hostName();

        $vpnProtoPorts = $this->profileConfig->vpnProtoPorts();
        if (0 !== \count($this->profileConfig->exposedVpnProtoPorts())) {
            $vpnProtoPorts = $this->profileConfig->exposedVpnProtoPorts();
        }

        $remoteProtoPortList = $this->remotePortProtoList($vpnProtoPorts);
        // make sure we have remotes
        // we assume that *without* tcpOnly we always have remotes,
        // otherwise it is a server configuration bug
        if ($this->tcpOnly && 0 === \count($remoteProtoPortList)) {
            throw new ClientConfigException('no TCP connection possible');
        }

        $clientConfig = [
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

        // remote entries
        foreach ($remoteProtoPortList as $remoteProtoPort) {
            $clientConfig[] = sprintf('remote %s %d %s', $hostName, (int) Binary::safeSubstr($remoteProtoPort, 4), Binary::safeSubstr($remoteProtoPort, 0, 3));
        }

        // convert the OpenVPN file to "Windows" format, no platform cares, but
        // in Notepad on Windows it looks not so great everything on one line
        // XXX it seems TunnelKit *does* care and wants Windows format?!
        // or maybe it just needs it to be consistent...
        return str_replace("\n", "\r\n", implode(PHP_EOL, $clientConfig));
    }

    /**
     * Pick a "normal" UDP and TCP port. Pick a "special" UDP and TCP
     * port.
     *
     * @return array<string>
     */
    public function remotePortProtoList(array $vpnProtoPorts): array
    {
        $specialUdpPorts = [];
        $specialTcpPorts = [];
        $normalUdpPorts = [];
        $normalTcpPorts = [];

        foreach ($vpnProtoPorts as $vpnProtoPort) {
            if (0 === strpos($vpnProtoPort, 'udp')) {
                if (\in_array($vpnProtoPort, ['udp/53', 'udp/443'], true)) {
                    $specialUdpPorts[] = $vpnProtoPort;

                    continue;
                }
                $normalUdpPorts[] = $vpnProtoPort;

                continue;
            }

            if (0 === strpos($vpnProtoPort, 'tcp')) {
                if (\in_array($vpnProtoPort, ['tcp/80', 'tcp/443'], true)) {
                    $specialTcpPorts[] = $vpnProtoPort;

                    continue;
                }
                $normalTcpPorts[] = $vpnProtoPort;

                continue;
            }
        }

        $clientPortList = [];
        if (!$this->tcpOnly) {
            $this->getItem($clientPortList, $normalUdpPorts);
            $this->getItem($clientPortList, $specialUdpPorts);
        }
        $this->getItem($clientPortList, $normalTcpPorts);
        $this->getItem($clientPortList, $specialTcpPorts);

        return $clientPortList;
    }

    /**
     * @param array<string> &$clientPortList
     * @param array<string> $pickFrom
     */
    private function getItem(array &$clientPortList, array $pickFrom): void
    {
        if (0 === \count($pickFrom)) {
            return;
        }

        switch ($this->remoteStrategy) {
            case self::STRATEGY_ALL:
                $clientPortList = array_merge($clientPortList, $pickFrom);

                break;

            case self::STRATEGY_RANDOM:
                $clientPortList[] = $pickFrom[random_int(0, \count($pickFrom) - 1)];

                break;

            default:
                $clientPortList[] = reset($pickFrom);

                break;
        }
    }
}
