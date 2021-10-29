<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OpenVpn;

use LC\Portal\IP;
use LC\Portal\OpenVpn\CA\CaInterface;
use LC\Portal\OpenVpn\CA\CertInfo;
use LC\Portal\ProfileConfig;
use RuntimeException;

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
        $processCount = \count($profileConfig->udpPortList()) + \count($profileConfig->tcpPortList());
        $allowedProcessCount = [1, 2, 4, 8, 16, 32, 64];
        if (!\in_array($processCount, $allowedProcessCount, true)) {
            // XXX introduce ServerConfigException?
            throw new RuntimeException('"udpPortList" and "tcpPortList" together must contain 1,2,4,8,16,32 or 64 entries');
        }
        $splitRangeFour = $profileConfig->rangeFour($nodeNumber)->split($processCount);
        $splitRangeSix = $profileConfig->rangeSix($nodeNumber)->split($processCount);
        $processConfig = [];
        $profileServerConfig = [];

        // XXX what follows is ugly! we should be able to do better! for one make getProcess static
        $processNumber = 0;
        foreach ($profileConfig->udpPortList() as $udpPort) {
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

        foreach ($profileConfig->tcpPortList() as $tcpPort) {
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
     * @param array{rangeFour:\LC\Portal\IP,rangeSix:\LC\Portal\IP,tunDev:int,proto:string,port:int,processNumber:int} $processConfig
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
            sprintf('max-clients %d', $rangeFourIp->numberOfHosts() - 2),
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

        if ($profileConfig->clientToClient()) {
            // XXX document that the administrator may need to push the range
            // and range6 routes as well when not in full-tunnel when wanting
            // to reach other clients on other OpenVPN processes
            $serverConfig[] = 'client-to-client';
        }

        // Routes
        $serverConfig = array_merge($serverConfig, self::getRoutes($profileConfig));

        // DNS
        $serverConfig = array_merge($serverConfig, self::getDns($rangeFourIp, $rangeSixIp, $profileConfig));

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
        if ($profileConfig->defaultGateway()) {
            $redirectFlags = ['def1', 'ipv6'];
            if ($profileConfig->blockLan()) {
                $redirectFlags[] = 'block-local';
            }

            $routeConfig[] = sprintf('push "redirect-gateway %s"', implode(' ', $redirectFlags));
            // XXX try again without 0/0... the horror!
            //$routeConfig[] = 'push "route 0.0.0.0 0.0.0.0"';
        }

        $routeList = $profileConfig->routeList();
        if (0 !== \count($routeList)) {
            foreach ($routeList as $route) {
                $routeIp = IP::fromIpPrefix($route);
                if (IP::IP_6 === $routeIp->family()) {
                    // IPv6
                    $routeConfig[] = sprintf('push "route-ipv6 %s"', (string) $routeIp);
                } else {
                    // IPv4
                    $routeConfig[] = sprintf('push "route %s %s"', $routeIp->address(), $routeIp->netmask());
                }
            }
        }

        $excludeRouteList = $profileConfig->excludeRouteList();
        if (0 !== \count($excludeRouteList)) {
            foreach ($excludeRouteList as $excludeRoute) {
                $routeIp = IP::fromIpPrefix($excludeRoute);
                if (IP::IP_6 === $routeIp->family()) {
                    // IPv6
                    $routeConfig[] = sprintf('push "route-ipv6 %s net_gateway_ipv6"', (string) $routeIp);
                } else {
                    // IPv4
                    $routeConfig[] = sprintf('push "route %s %s net_gateway"', $routeIp->address(), $routeIp->netmask());
                }
            }
        }

        return $routeConfig;
    }

    /**
     * @return array<string>
     */
    private static function getDns(IP $rangeFourIp, IP $rangeSixIp, ProfileConfig $profileConfig): array
    {
        $dnsEntries = [];
        if ($profileConfig->defaultGateway()) {
            // prevent DNS leakage on Windows when VPN is default gateway
            $dnsEntries[] = 'push "block-outside-dns"';
        }
        $dnsList = $profileConfig->dnsServerList();
        foreach ($dnsList as $dnsAddress) {
            // replace the macros by IP addresses (LOCAL_DNS)
            if ('@GW4@' === $dnsAddress) {
                $dnsAddress = $rangeFourIp->firstHost();
            }
            if ('@GW6@' === $dnsAddress) {
                $dnsAddress = $rangeSixIp->firstHost();
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
}
