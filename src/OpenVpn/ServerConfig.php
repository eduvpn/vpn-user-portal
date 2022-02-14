<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\OpenVpn;

use RuntimeException;
use Vpn\Portal\Cfg\ProfileConfig;
use Vpn\Portal\Ip;
use Vpn\Portal\IpNetList;
use Vpn\Portal\OpenVpn\CA\CaInterface;
use Vpn\Portal\OpenVpn\CA\CertInfo;

class ServerConfig
{
    public const VPN_USER = 'openvpn';
    public const VPN_GROUP = 'openvpn';
    public const LIBEXEC_DIR = '/usr/libexec/vpn-server-node';

    private CaInterface $ca;
    private TlsCrypt $tlsCrypt;
    private int $tunDev = 0;

    public function __construct(CaInterface $ca, TlsCrypt $tlsCrypt)
    {
        $this->ca = $ca;
        $this->tlsCrypt = $tlsCrypt;
    }

    /**
     * @return array<string,string>
     */
    public function getProfile(ProfileConfig $profileConfig, int $nodeNumber, bool $preferAes): array
    {
        $certInfo = $this->ca->serverCert($profileConfig->hostName($nodeNumber), $profileConfig->profileId());

        // make sure the number of OpenVPN server processes is a factor of two
        // so we can easily split the assigned IP space
        $processCount = \count($profileConfig->oUdpPortList()) + \count($profileConfig->oTcpPortList());
        $allowedProcessCount = [1, 2, 4, 8, 16, 32, 64];
        if (!\in_array($processCount, $allowedProcessCount, true)) {
            // XXX introduce ServerConfigException?
            throw new RuntimeException('"oUdpPortList" and "oTcpPortList" together must contain 1,2,4,8,16,32 or 64 entries');
        }
        $splitRangeFour = $profileConfig->oRangeFour($nodeNumber)->split($processCount);
        $splitRangeSix = $profileConfig->oRangeSix($nodeNumber)->split($processCount);
        $processConfig = [];
        $profileServerConfig = [];

        // XXX what follows is ugly! we should be able to do better! for one make getProcess static
        $processNumber = 0;
        foreach ($profileConfig->oUdpPortList() as $udpPort) {
            $processConfig['rangeFour'] = $splitRangeFour[$processNumber];
            $processConfig['rangeSix'] = $splitRangeSix[$processNumber];
            $processConfig['tunDev'] = $this->tunDev;
            $processConfig['proto'] = 'udp6';
            $processConfig['port'] = $udpPort;
            $processConfig['processNumber'] = $processNumber;
            $configName = sprintf('%s-%d.conf', $profileConfig->profileId(), $processNumber);
            $profileServerConfig[$configName] = $this->getProcess($profileConfig, $processConfig, $certInfo, $preferAes);
            ++$processNumber;
            ++$this->tunDev;
        }

        foreach ($profileConfig->oTcpPortList() as $tcpPort) {
            $processConfig['rangeFour'] = $splitRangeFour[$processNumber];
            $processConfig['rangeSix'] = $splitRangeSix[$processNumber];
            $processConfig['tunDev'] = $this->tunDev;
            $processConfig['proto'] = 'tcp6-server';
            $processConfig['port'] = $tcpPort;
            $processConfig['processNumber'] = $processNumber;
            $configName = sprintf('%s-%d.conf', $profileConfig->profileId(), $processNumber);
            $profileServerConfig[$configName] = $this->getProcess($profileConfig, $processConfig, $certInfo, $preferAes);
            ++$processNumber;
            ++$this->tunDev;
        }

        return $profileServerConfig;
    }

    /**
     * @param array{rangeFour:\Vpn\Portal\Ip,rangeSix:\Vpn\Portal\Ip,tunDev:int,proto:string,port:int,processNumber:int} $processConfig
     */
    private function getProcess(ProfileConfig $profileConfig, array $processConfig, CertInfo $certInfo, bool $preferAes): string
    {
        $rangeFourIp = $processConfig['rangeFour'];
        $rangeSixIp = $processConfig['rangeSix'];

        // static options
        $serverConfig = [
            '# OpenVPN Server Config | Automatically Generated | Do NOT modify!',
            'verb 3',
            'dev-type tun',
            sprintf('user %s', self::VPN_USER),
            sprintf('group %s', self::VPN_GROUP),
            'topology subnet',
            'persist-key',
            'persist-tun',
            'remote-cert-tls client',

            // Only ECDHE
            'dh none',
            // >= TLSv1.3
            'tls-version-min 1.3',

            self::getDataCiphers($preferAes),

            // renegotiate data channel key every 10 hours instead of every hour
            sprintf('reneg-sec %d', 10 * 60 * 60),
            sprintf('client-connect %s/client-connect', self::LIBEXEC_DIR),
            sprintf('client-disconnect %s/client-disconnect', self::LIBEXEC_DIR),
            sprintf('server %s %s', (string) $rangeFourIp->network(), $rangeFourIp->netmask()),
            sprintf('server-ipv6 %s', (string) $rangeSixIp),
            // OpenVPN's pool management does NOT include the last usable IP in
            // the range in the pool, and obviously not the first one as that
            // will be used by OpenVPN itself. So, if you have the range
            // 10.3.240/25 that would give room for 128 - 3 (network,
            // broadcast, OpenVPN) = 125 clients. But OpenVPN thinks
            // differently:
            //
            //      ifconfig_pool_start = 10.3.240.2
            //      ifconfig_pool_end = 10.3.240.125
            //
            // it keeps 10.3.240.126 out of the pool, which is a totally valid
            // address, but alas, won't be available to clients... So we only
            // have *124* possible client IPs to be issued...
            //
            // the same is true for the smallest possible network (/29):
            //      ifconfig_pool_start = 10.3.240.2
            //      ifconfig_pool_end = 10.3.240.5
            //
            // We MUST set max-clients to this number as that will cause a nice
            // timout on the OpenVPN process for the client, until it will try
            // the next available OpenVPN process...
            // @see https://community.openvpn.net/openvpn/ticket/1347
            // @see https://community.openvpn.net/openvpn/ticket/1348
            sprintf('max-clients %d', $rangeFourIp->numberOfHostsFour() - 2),
            // technically we do NOT need "keepalive" (ping/ping-restart) on
            // TCP, but it seems we do need it to avoid clients disconnecting
            // after 2 minutes of inactivity when the first (previous?) remote
            // was UDP and the default of 120s was set and not properly reset
            // when switching to a TCP remote... This is pure speculation, but
            // having "keepalive" on TCP does keep clients over TCP
            // connected, so it does something at least...
            // @see https://sourceforge.net/p/openvpn/mailman/message/37168823/
            'keepalive 10 60',
            'script-security 2',
            sprintf('dev tun%d', $processConfig['tunDev']),
            sprintf('port %d', $processConfig['port']),
            sprintf('management /run/openvpn-server/%s-%d.sock unix', $profileConfig->profileId(), $processConfig['processNumber']),
            sprintf('setenv PROFILE_ID %s', $profileConfig->profileId()),
            sprintf('proto %s', $processConfig['proto']),

            '<ca>',
            $this->ca->caCert()->pemCert(),
            '</ca>',
            '<cert>',
            $certInfo->pemCert(),
            '</cert>',
            '<key>',
            $certInfo->pemKey(),
            '</key>',
            '<tls-crypt>',
            $this->tlsCrypt->get($profileConfig->profileId()),
            '</tls-crypt>',
        ];

        if (!$profileConfig->oEnableLog()) {
            $serverConfig[] = 'log /dev/null';
        }

        if ('tcp-server' === $processConfig['proto'] || 'tcp6-server' === $processConfig['proto']) {
            $serverConfig[] = 'tcp-nodelay';
        }

        if ('udp' === $processConfig['proto'] || 'udp6' === $processConfig['proto']) {
            // notify the clients to reconnect to the exact same OpenVPN process
            // when the OpenVPN process restarts...
            $serverConfig[] = 'explicit-exit-notify 1';
            // also ask the clients on UDP to tell us when they leave...
            // https://github.com/OpenVPN/openvpn/commit/422ecdac4a2738cd269361e048468d8b58793c4e
            $serverConfig[] = 'push "explicit-exit-notify 1"';
        }

        // Routes
        $serverConfig = array_merge($serverConfig, self::getRoutes($profileConfig));

        // DNS
        $serverConfig = array_merge($serverConfig, self::getDns($profileConfig));

        return implode(PHP_EOL, $serverConfig);
    }

    private static function getDataCiphers(bool $preferAes): string
    {
        if ($preferAes) {
            return 'data-ciphers AES-256-GCM:CHACHA20-POLY1305';
        }

        return 'data-ciphers CHACHA20-POLY1305:AES-256-GCM';
    }

    /**
     * @return array<string>
     */
    private static function getRoutes(ProfileConfig $profileConfig): array
    {
        $routeConfig = [];
        $routeList = new IpNetList();
        if ($profileConfig->defaultGateway()) {
            // send all IPv4 and IPv6 traffic over the VPN tunnel
            $redirectFlags = ['def1', 'ipv6'];
            if ($profileConfig->oBlockLan()) {
                // Block  access to local LAN
                $redirectFlags[] = 'block-local';
            }
            $routeConfig[] = sprintf('push "redirect-gateway %s"', implode(' ', $redirectFlags));
            // quirk needed for Windows otherwise Windows thinks there is no
            // Internet connectivity
            $routeList->add(Ip::fromIpPrefix('0.0.0.0/0'));
        }

        // (additional) prefixes to send over the VPN
        foreach ($profileConfig->routeList() as $routeIpPrefix) {
            $routeList->add(Ip::fromIpPrefix($routeIpPrefix));
        }
        foreach ($routeList->ls() as $routeIpPrefix) {
            if (Ip::IP_6 === $routeIpPrefix->family()) {
                // IPv6
                $routeConfig[] = sprintf('push "route-ipv6 %s"', (string) $routeIpPrefix);
            } else {
                // IPv4
                $routeConfig[] = sprintf('push "route %s %s"', $routeIpPrefix->address(), $routeIpPrefix->netmask());
            }
        }

        // prefixes NOT to send over the VPN
        $excludeRouteList = new IpNetList();
        foreach ($profileConfig->excludeRouteList() as $routeIpPrefix) {
            $excludeRouteList->add(Ip::fromIpPrefix($routeIpPrefix));
        }
        foreach ($excludeRouteList->ls() as $routeIpPrefix) {
            if (Ip::IP_6 === $routeIpPrefix->family()) {
                // IPv6
                $routeConfig[] = sprintf('push "route-ipv6 %s net_gateway_ipv6"', (string) $routeIpPrefix);
            } else {
                // IPv4
                $routeConfig[] = sprintf('push "route %s %s" net_gateway', $routeIpPrefix->address(), $routeIpPrefix->netmask());
            }
        }

        return $routeConfig;
    }

    /**
     * @return array<string>
     */
    private static function getDns(ProfileConfig $profileConfig): array
    {
        $dnsServerList = $profileConfig->dnsServerList();

        $dnsEntries = [];

        // push DNS servers when default gateway is set, or there are some
        // search domains specified
        if ($profileConfig->defaultGateway() || 0 !== \count($profileConfig->dnsSearchDomainList())) {
            foreach ($dnsServerList as $dnsAddress) {
                $dnsEntries[] = sprintf('push "dhcp-option DNS %s"', $dnsAddress);
            }
        }

        // prevent DNS leakage on Windows when VPN is default gateway and
        // VPN has DNS servers
        if ($profileConfig->defaultGateway() && 0 !== \count($dnsServerList)) {
            $dnsEntries[] = 'push "block-outside-dns"';
        }

        // provide connection specific DNS domains to use for querying
        // the DNS server when default gateway is not true
        if (!$profileConfig->defaultGateway() && 0 !== \count($dnsServerList)) {
            foreach ($profileConfig->dnsSearchDomainList() as $dnsSearchDomain) {
                $dnsEntries[] = sprintf('push "dhcp-option DOMAIN-SEARCH %s"', $dnsSearchDomain);
            }
        }

        return $dnsEntries;
    }
}
