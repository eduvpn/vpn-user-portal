<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Node;

use DateTime;
use LC\Portal\CA\CaInterface;
use LC\Portal\Config\PortalConfig;
use LC\Portal\Config\ProfileConfig;
use LC\Portal\IP;
use LC\Portal\TlsCrypt;
use RuntimeException;

class ServerConfig
{
    /** @var \LC\Portal\Config\PortalConfig */
    private $portalConfig;

    /** @var \LC\Portal\CA\CaInterface */
    private $ca;

    /** @var \LC\Portal\TlsCrypt */
    private $tlsCrypt;

    /** @var string */
    private $libExecDir;

    /** @var string */
    private $vpnUser;

    /** @var string */
    private $vpnGroup;

    /** @var \DateTime */
    private $dateTime;

    /**
     * @param \LC\Portal\Config\PortalConfig $portalConfig
     * @param \LC\Portal\CA\CaInterface      $ca
     * @param \LC\Portal\TlsCrypt            $tlsCrypt
     * @param string                         $libExecDir
     * @param string                         $vpnUser
     * @param string                         $vpnGroup
     */
    public function __construct(PortalConfig $portalConfig, CaInterface $ca, TlsCrypt $tlsCrypt, $libExecDir, $vpnUser, $vpnGroup)
    {
        $this->portalConfig = $portalConfig;
        $this->ca = $ca;
        $this->tlsCrypt = $tlsCrypt;
        $this->libExecDir = $libExecDir;
        $this->vpnUser = $vpnUser;
        $this->vpnGroup = $vpnGroup;
        $this->dateTime = new DateTime();
    }

    /**
     * @return array<string,string>
     */
    public function getConfigList()
    {
        $configList = [];
        $commonName = sprintf('LC.%s', $this->dateTime->format('YmdHis'));
        $certData = array_merge(
            $this->ca->serverCert($commonName),
            [
                'ca' => $this->ca->caCert(),
                'tls-crypt' => $this->tlsCrypt->get(),
            ]
        );
        $profileList = $this->portalConfig->getProfileConfigList();
        foreach ($profileList as $profileId => $profileConfig) {
            $configList = array_merge(
                $configList,
                $this->getProfileConfig($certData, $profileId, $profileConfig)
            );
        }

        return $configList;
    }

    /**
     * @param array{cert:string, key:string, valid_from:int, valid_to:int, ca:string, tls-crypt:string} $certData
     * @param string                                                                                    $profileId
     * @param \LC\Portal\Config\ProfileConfig                                                           $profileConfig
     *
     * @return array<string,string>
     */
    private function getProfileConfig(array $certData, $profileId, ProfileConfig $profileConfig)
    {
        $profileConfigList = [];

        $processCount = \count($profileConfig->getVpnProtoPortList());
        $allowedProcessCount = [1, 2, 4, 8, 16, 32, 64];
        if (!\in_array($processCount, $allowedProcessCount, true)) {
            throw new RuntimeException('"vpnProtoPortList" must contain 1, 2, 4, 8, 16, 32 or 64 entries');
        }

        $rangeFour = new IP($profileConfig->getRangeFour());
        $rangeSix = new IP($profileConfig->getRangeSix());
        $splitRangeFour = $rangeFour->split($processCount);
        $splitRangeSix = $rangeSix->split($processCount);

        $profileNumber = $profileConfig->getProfileNumber();

        $processConfig = [];
        for ($i = 0; $i < $processCount; ++$i) {
            $processConfig['rangeFour'] = $splitRangeFour[$i];
            $processConfig['rangeSix'] = $splitRangeSix[$i];
            $processConfig['dev'] = sprintf('tun-%d-%d', $profileNumber, $i);
            $processConfig['proto'] = self::getProto($profileConfig, $i);
            $processConfig['port'] = self::getPort($profileConfig, $i);
            $processConfig['managementPort'] = 11940 + self::toPort($profileNumber, $i);
            $profileConfigList[sprintf('%s-%d', $profileId, $i)] = $this->getProcessConfig($certData, $profileId, $profileConfig, $processConfig);
        }

        return $profileConfigList;
    }

    /**
     * @param string $profileId
     *
     * @return string
     */
    private function getProcessConfig(array $certData, $profileId, ProfileConfig $profileConfig, array $processConfig)
    {
        $rangeFourIp = new IP($processConfig['rangeFour']);
        $rangeSixIp = new IP($processConfig['rangeSix']);

        // static options
        $serverConfig = [
            'verb 3',
            'dev-type tun',
            sprintf('user %s', $this->vpnUser),
            sprintf('group %s', $this->vpnGroup),
            'topology subnet',
            'persist-key',
            'persist-tun',
            'remote-cert-tls client',
            'tls-version-min 1.2',
            'tls-cipher TLS-ECDHE-RSA-WITH-AES-256-GCM-SHA384',
            'dh none', // Only ECDHE
            'ncp-ciphers AES-256-GCM',  // only AES-256-GCM
            'cipher AES-256-GCM',       // only AES-256-GCM
            'auth none',
            sprintf('client-connect %s/client-connect', $this->libExecDir),
            sprintf('client-disconnect %s/client-disconnect', $this->libExecDir),
            sprintf('server %s %s', $rangeFourIp->getNetwork(), $rangeFourIp->getNetmask()),
            sprintf('server-ipv6 %s', $rangeSixIp->getAddressPrefix()),
            sprintf('max-clients %d', $rangeFourIp->getNumberOfHosts() - 1),
            'script-security 2',
            sprintf('dev %s', $processConfig['dev']),
            sprintf('port %d', $processConfig['port']),
            sprintf('management %s %d', $profileConfig->getManagementIp(), $processConfig['managementPort']),
            sprintf('setenv PROFILE_ID %s', $profileId),
            sprintf('proto %s', $processConfig['proto']),
            sprintf('local %s', $profileConfig->getListen()),
        ];

        if (!$profileConfig->getEnableLog()) {
            $serverConfig[] = 'log /dev/null';
        }

        if ('tcp-server' === $processConfig['proto'] || 'tcp6-server' === $processConfig['proto']) {
            $serverConfig[] = 'tcp-nodelay';
        }

        if ('udp' === $processConfig['proto'] || 'udp6' === $processConfig['proto']) {
            // notify the clients to reconnect to the exact same OpenVPN process
            // when the OpenVPN process restarts...
            $serverConfig[] = 'keepalive 10 60';
            $serverConfig[] = 'explicit-exit-notify 1';
            // also ask the clients on UDP to tell us when they leave...
            // https://github.com/OpenVPN/openvpn/commit/422ecdac4a2738cd269361e048468d8b58793c4e
            $serverConfig[] = 'push "explicit-exit-notify 1"';
        }

        // Routes
        $serverConfig = array_merge($serverConfig, self::getRoutes($profileConfig));

        // DNS
        $serverConfig = array_merge($serverConfig, self::getDns($rangeFourIp, $rangeSixIp, $profileConfig));

        // Client-to-client
        $serverConfig = array_merge($serverConfig, self::getClientToClient($profileConfig));

        sort($serverConfig);

        $serverConfig = array_merge(
            [
                '#',
                '# OpenVPN Server Configuration',
                '#',
            ],
            $serverConfig
        );

        // add all inline data
        $serverConfig = array_merge(
            $serverConfig,
            [
                '<ca>',
                ($certData['ca']),
                '</ca>',
                '<cert>',
                ($certData['cert']),
                '</cert>',
                '<key>',
                trim($certData['key']),
                '</key>',
                '<tls-crypt>',
                trim($certData['tls-crypt']),
                '</tls-crypt>',
            ]
        );

        return implode(PHP_EOL, $serverConfig).PHP_EOL;
    }

    /**
     * @param string $listenAddress
     * @param string $proto
     *
     * @return string
     */
    private static function getFamilyProto($listenAddress, $proto)
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

    /**
     * @param \LC\Portal\Config\ProfileConfig $profileConfig
     * @param int                             $i
     *
     * @return string
     */
    private static function getProto(ProfileConfig $profileConfig, $i)
    {
        $vpnProtoPortList = $profileConfig->getVpnProtoPortList();
        list($vpnProto, $vpnPort) = explode('/', $vpnProtoPortList[$i]);

        return self::getFamilyProto($profileConfig->getListen(), $vpnProto);
    }

    /**
     * @param \LC\Portal\Config\ProfileConfig $profileConfig
     * @param int                             $i
     *
     * @return int
     */
    private static function getPort(ProfileConfig $profileConfig, $i)
    {
        $vpnProtoPortList = $profileConfig->getVpnProtoPortList();
        list($vpnProto, $vpnPort) = explode('/', $vpnProtoPortList[$i]);

        return (int) $vpnPort;
    }

    /**
     * @return array
     */
    private static function getRoutes(ProfileConfig $profileConfig)
    {
        if ($profileConfig->getDefaultGateway()) {
            $redirectFlags = ['def1', 'ipv6'];
            if ($profileConfig->getBlockLan()) {
                $redirectFlags[] = 'block-local';
            }

            return [
                sprintf('push "redirect-gateway %s"', implode(' ', $redirectFlags)),
            ];
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
        foreach ($profileConfig->getRoutes() as $route) {
            $routeIp = new IP($route);
            if (6 === $routeIp->getFamily()) {
                // IPv6
                $routeConfig[] = sprintf('push "route-ipv6 %s"', $routeIp->getAddressPrefix());
            } else {
                // IPv4
                $routeConfig[] = sprintf('push "route %s %s"', $routeIp->getAddress(), $routeIp->getNetmask());
            }
        }

        return $routeConfig;
    }

    /**
     * @return array
     */
    private static function getDns(IP $rangeFourIp, IP $rangeSixIp, ProfileConfig $profileConfig)
    {
        $dnsEntries = [];
        if ($profileConfig->getDefaultGateway()) {
            // prevent DNS leakage on Windows when VPN is default gateway
            $dnsEntries[] = 'push "block-outside-dns"';
        }
        $dnsList = $profileConfig->getDns();
        foreach ($dnsList as $dnsAddress) {
            // replace the macros by IP addresses (LOCAL_DNS)
            if ('@GW4@' === $dnsAddress) {
                $dnsAddress = $rangeFourIp->getFirstHost();
            }
            if ('@GW6@' === $dnsAddress) {
                $dnsAddress = $rangeSixIp->getFirstHost();
            }
            $dnsEntries[] = sprintf('push "dhcp-option DNS %s"', $dnsAddress);
        }

        return $dnsEntries;
    }

    /**
     * @return array
     */
    private static function getClientToClient(ProfileConfig $profileConfig)
    {
        if (!$profileConfig->getClientToClient()) {
            return [];
        }

        $rangeFourIp = new IP($profileConfig->getRangeFour());
        $rangeSixIp = new IP($profileConfig->getRangeSix());

        return [
            'client-to-client',
            sprintf('push "route %s %s"', $rangeFourIp->getAddress(), $rangeFourIp->getNetmask()),
            sprintf('push "route-ipv6 %s"', $rangeSixIp->getAddressPrefix()),
        ];
    }

    /**
     * @param int $profileNumber
     * @param int $processNumber
     *
     * @return int
     */
    private static function toPort($profileNumber, $processNumber)
    {
        // we have 2^16 - 11940 ports available for management ports, so let's
        // say we have 2^14 ports available to distribute over profiles and
        // processes, let's take 12 bits, so we have 64 profiles with each 64
        // processes...
        return ($profileNumber - 1 << 6) | $processNumber;
    }
}
