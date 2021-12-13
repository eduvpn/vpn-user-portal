<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\WireGuard;

use Vpn\Portal\Base64;
use Vpn\Portal\ClientConfigInterface;
use Vpn\Portal\Exception\QrCodeException;
use Vpn\Portal\Ip;
use Vpn\Portal\IpNetList;
use Vpn\Portal\ProfileConfig;
use Vpn\Portal\QrCode;

/**
 * Represent a WireGuard client configuration file.
 */
class ClientConfig implements ClientConfigInterface
{
    private int $nodeNumber;
    private ProfileConfig $profileConfig;
    private ?string $privateKey;
    private string $ipFour;
    private string $ipSix;
    private string $serverPublicKey;
    private int $wgPort;

    public function __construct(int $nodeNumber, ProfileConfig $profileConfig, ?string $privateKey, string $ipFour, string $ipSix, string $serverPublicKey, int $wgPort)
    {
        $this->nodeNumber = $nodeNumber;
        $this->profileConfig = $profileConfig;
        $this->privateKey = $privateKey;
        $this->ipFour = $ipFour;
        $this->ipSix = $ipSix;
        $this->serverPublicKey = $serverPublicKey;
        $this->wgPort = $wgPort;
    }

    public function contentType(): string
    {
        return 'application/x-wireguard-profile';
    }

    public function get(): string
    {
        $routeList = new IpNetList();
        if ($this->profileConfig->defaultGateway()) {
            $routeList->add(Ip::fromIpPrefix('0.0.0.0/0'));
            $routeList->add(Ip::fromIpPrefix('::/0'));
        }
        // add the (additional) prefixes we want
        foreach ($this->profileConfig->routeList() as $routeIpPrefix) {
            $routeList->add(Ip::fromIpPrefix($routeIpPrefix));
        }
        // remove the prefixes we don't want
        foreach ($this->profileConfig->excludeRouteList() as $routeIpPrefix) {
            $routeList->remove(Ip::fromIpPrefix($routeIpPrefix));
        }

        $output = [];
        $output[] = '[Interface]';
        if (null !== $this->privateKey) {
            $output[] = 'PrivateKey = '.$this->privateKey;
        }
        $output[] = 'Address = '.$this->ipFour.'/'.$this->profileConfig->wRangeFour($this->nodeNumber)->prefix().','.$this->ipSix.'/'.$this->profileConfig->wRangeSix($this->nodeNumber)->prefix();

        $dnsEntries = $this->getDns($this->profileConfig);
        if (0 !== \count($dnsEntries)) {
            $output[] = 'DNS = '.implode(',', $dnsEntries);
        }
        $output[] = '';
        $output[] = '[Peer]';
        $output[] = 'PublicKey = '.$this->serverPublicKey;
        $output[] = 'AllowedIPs = '.implode(',', $routeList->ls());
        $output[] = 'Endpoint = '.$this->profileConfig->hostName($this->nodeNumber).':'.(string) $this->wgPort;

        return implode(PHP_EOL, $output);
    }

    public function getQr(): ?string
    {
        try {
            return Base64::encode(QrCode::generate($this->get()));
        } catch (QrCodeException $e) {
            return null;
        }
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
            $dnsEntries = array_merge($dnsEntries, $dnsServerList);
        }

        // provide connection specific DNS domains to use for querying
        // the DNS server when default gateway is not true
        if (!$profileConfig->defaultGateway() && 0 !== \count($dnsServerList)) {
            $dnsEntries = array_merge($dnsEntries, $profileConfig->dnsSearchDomainList());
        }

        return $dnsEntries;
    }
}
