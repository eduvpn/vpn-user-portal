<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\CA\CaInterface;
use LC\Portal\CA\CertInfo;
use RangeException;
use RuntimeException;

class OpenVpnServerConfig
{
    const VPN_USER = 'openvpn';
    const VPN_GROUP = 'openvpn';
    const LIBEXEC_DIR = '/usr/libexec/vpn-server-node';

    private CaInterface $ca;
    private TlsCrypt $tlsCrypt;

    public function __construct(CaInterface $ca, TlsCrypt $tlsCrypt)
    {
        $this->ca = $ca;
        $this->tlsCrypt = $tlsCrypt;
    }

    /**
     * @return array<string,string>
     */
    public function getProfile(ProfileConfig $profileConfig): array
    {
        $certInfo = $this->ca->serverCert($profileConfig->hostName(), $profileConfig->profileId());
        $range = new IP($profileConfig->range());
        $range6 = new IP($profileConfig->range6());
        $processCount = \count($profileConfig->vpnProtoPorts());
        $allowedProcessCount = [1, 2, 4, 8, 16, 32, 64];
        if (!\in_array($processCount, $allowedProcessCount, true)) {
            throw new RuntimeException('"vpnProtoPorts" must contain 1,2,4,8,16,32 or 64 entries');
        }
        $splitRange = $range->split($processCount);
        $splitRange6 = $range6->split($processCount);
        $profileNumber = $profileConfig->profileNumber();
        $processConfig = [];
        $profileServerConfig = [];
        for ($i = 0; $i < $processCount; ++$i) {
            [$proto, $port] = self::getProtoPort($profileConfig->vpnProtoPorts(), $profileConfig->listenIp())[$i];
            $processConfig['range'] = $splitRange[$i];
            $processConfig['range6'] = $splitRange6[$i];
            $processConfig['dev'] = sprintf('tun%d', self::toPort($profileConfig->profileNumber(), $i));
            $processConfig['proto'] = $proto;
            $processConfig['port'] = $port;
            $processConfig['local'] = $profileConfig->listenIp();
            $processConfig['managementPort'] = 11940 + self::toPort($profileNumber, $i);

            $configName = sprintf('%s-%d.conf', $profileConfig->profileId(), $i);
            $profileServerConfig[$configName] = $this->getProcess($profileConfig, $processConfig, $certInfo);
        }

        return $profileServerConfig;
    }

    private static function getFamilyProto(string $listenAddress, string $proto): string
    {
        $v6 = false !== strpos($listenAddress, ':');
        if ('udp' === $proto) {
            return $v6 ? 'udp6' : 'udp';
        }
        if ('tcp' === $proto) {
            return $v6 ? 'tcp6-server' : 'tcp-server';
        }

        throw new RuntimeException('only "tcp" and "udp" are supported as protocols');
    }

    private static function getProtoPort(array $vpnProcesses, string $listenAddress): array
    {
        $convertedPortProto = [];

        foreach ($vpnProcesses as $vpnProcess) {
            [$proto, $port] = explode('/', $vpnProcess);
            $convertedPortProto[] = [self::getFamilyProto($listenAddress, $proto), $port];
        }

        return $convertedPortProto;
    }

    private function getProcess(ProfileConfig $profileConfig, array $processConfig, CertInfo $certInfo): string
    {
        /** @var \LC\Portal\IP $rangeIp */
        $rangeIp = $processConfig['range'];
        /** @var \LC\Portal\IP $range6Ip */
        $range6Ip = $processConfig['range6'];

        // static options
        $serverConfig = [
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

            // only allow AES-256-GCM
            'data-ciphers AES-256-GCM',

            // renegotiate data channel key every 10 hours instead of every hour
            sprintf('reneg-sec %d', 10 * 60 * 60),
            sprintf('client-connect %s/client-connect', self::LIBEXEC_DIR),
            sprintf('client-disconnect %s/client-disconnect', self::LIBEXEC_DIR),
            sprintf('server %s %s', (string) $rangeIp->getNetwork(), $rangeIp->getNetmask()),
            sprintf('server-ipv6 %s', (string) $range6Ip),
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
            sprintf('max-clients %d', $rangeIp->getNumberOfHosts() - 2),
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
            sprintf('dev %s', $processConfig['dev']),
            sprintf('port %d', $processConfig['port']),
            sprintf('management 127.0.0.1 %d', $processConfig['managementPort']),
            sprintf('setenv PROFILE_ID %s', $profileConfig->profileId()),
            sprintf('proto %s', $processConfig['proto']),
            sprintf('local %s', $processConfig['local']),

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

        if (!$profileConfig->enableLog()) {
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
        $serverConfig = array_merge($serverConfig, self::getDns($rangeIp, $range6Ip, $profileConfig));

        // Client-to-client
        $serverConfig = array_merge($serverConfig, self::getClientToClient($profileConfig));

        array_unshift($serverConfig, '# OpenVPN Server Config | Automatically Generated | Do NOT modify!');

        return implode(\PHP_EOL, $serverConfig);
    }

    /**
     * @return array<string>
     */
    private static function getRoutes(ProfileConfig $profileConfig): array
    {
        if ($profileConfig->defaultGateway()) {
            $redirectFlags = ['def1', 'ipv6'];
            if ($profileConfig->blockLan()) {
                $redirectFlags[] = 'block-local';
            }

            return [
                sprintf('push "redirect-gateway %s"', implode(' ', $redirectFlags)),
                'push "route 0.0.0.0 0.0.0.0"',
            ];
        }

        $routeList = $profileConfig->routes();
        if (0 === \count($routeList)) {
            return [];
        }

        // Always set a route to the remote host through the client's default
        // gateway to avoid problems when the "split routes" pushed also
        // contain a range with the public IP address of the VPN server.
        // When connecting to a VPN server _over_ IPv6, OpenVPN takes care of
        // this all by itself by setting a /128 through the client's original
        // IPv6 gateway
        $routeConfig = [
            'push "route remote_host 255.255.255.255 net_gateway"',
        ];

        // there may be some routes specified, push those, and not the default
        foreach ($routeList as $route) {
            $routeIp = new IP($route);
            if (6 === $routeIp->getFamily()) {
                // IPv6
                $routeConfig[] = sprintf('push "route-ipv6 %s"', (string) $routeIp);
            } else {
                // IPv4
                $routeConfig[] = sprintf('push "route %s %s"', $routeIp->getAddress(), $routeIp->getNetmask());
            }
        }

        return $routeConfig;
    }

    /**
     * @return array<string>
     */
    private static function getDns(IP $rangeIp, IP $range6Ip, ProfileConfig $profileConfig): array
    {
        $dnsEntries = [];
        if ($profileConfig->defaultGateway()) {
            // prevent DNS leakage on Windows when VPN is default gateway
            $dnsEntries[] = 'push "block-outside-dns"';
        }
        $dnsList = $profileConfig->dns();
        foreach ($dnsList as $dnsAddress) {
            // replace the macros by IP addresses (LOCAL_DNS)
            if ('@GW4@' === $dnsAddress) {
                $dnsAddress = $rangeIp->getFirstHost()->getAddress();
            }
            if ('@GW6@' === $dnsAddress) {
                $dnsAddress = $range6Ip->getFirstHost()->getAddress();
            }
            $dnsEntries[] = sprintf('push "dhcp-option DNS %s"', $dnsAddress);
        }

        // push DOMAIN
        if (null !== $dnsDomain = $profileConfig->dnsDomain()) {
            $dnsEntries[] = sprintf('push "dhcp-option DOMAIN %s"', $dnsDomain);
        }
        // push DOMAIN-SEARCH
        $dnsDomainSearchList = $profileConfig->dnsDomainSearch();
        foreach ($dnsDomainSearchList as $dnsDomainSearch) {
            $dnsEntries[] = sprintf('push "dhcp-option DOMAIN-SEARCH %s"', $dnsDomainSearch);
        }

        return $dnsEntries;
    }

    /**
     * @return array<string>
     */
    private static function getClientToClient(ProfileConfig $profileConfig): array
    {
        if (!$profileConfig->clientToClient()) {
            return [];
        }

        $rangeIp = new IP($profileConfig->range());
        $range6Ip = new IP($profileConfig->range6());

        return [
            'client-to-client',
            sprintf('push "route %s %s"', $rangeIp->getAddress(), $rangeIp->getNetmask()),
            sprintf('push "route-ipv6 %s"', (string) $range6Ip),
        ];
    }

    private static function toPort(int $profileNumber, int $processNumber): int
    {
        if (1 > $profileNumber || 64 < $profileNumber) {
            throw new RangeException('1 <= profileNumber <= 64');
        }

        if (0 > $processNumber || 64 <= $processNumber) {
            throw new RangeException('0 <= processNumber < 64');
        }

        // we have 2^16 - 11940 ports available for management ports, so let's
        // say we have 2^14 ports available to distribute over profiles and
        // processes, let's take 12 bits, so we have 64 profiles with each 64
        // processes...
        return ($profileNumber - 1 << 6) | $processNumber;
    }
}
