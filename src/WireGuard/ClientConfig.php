<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\WireGuard;

use LC\Portal\Base64;
use LC\Portal\ClientConfigInterface;
use LC\Portal\Exception\QrCodeException;
use LC\Portal\IP;
use LC\Portal\IPList;
use LC\Portal\ProfileConfig;
use LC\Portal\QrCode;

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
        $routeList = new IPList();
        if ($this->profileConfig->defaultGateway()) {
            $routeList->add(IP::fromIpPrefix('0.0.0.0/0'));
            $routeList->add(IP::fromIpPrefix('::/0'));
        }
        // add the (additional) prefixes we want
        foreach ($this->profileConfig->routeList() as $routeIpPrefix) {
            $routeList->add(IP::fromIpPrefix($routeIpPrefix));
        }
        // remove the prefixes we don't want
        foreach ($this->profileConfig->excludeRouteList() as $routeIpPrefix) {
            $routeList->remove(IP::fromIpPrefix($routeIpPrefix));
        }

        $output = [];
        $output[] = '[Interface]';
        if (null !== $this->privateKey) {
            $output[] = 'PrivateKey = '.$this->privateKey;
        }
        $output[] = 'Address = '.$this->ipFour.'/'.$this->profileConfig->wRangeFour($this->nodeNumber)->prefix().','.$this->ipSix.'/'.$this->profileConfig->wRangeSix($this->nodeNumber)->prefix();
        if (0 !== \count($this->profileConfig->dnsServerList())) {
            $output[] = 'DNS = '.implode(',', $this->dns());
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
    private function dns(): array
    {
        $dnsServerList = [];
        foreach ($this->profileConfig->dnsServerList() as $configDnsServer) {
            if ('@GW4@' === $configDnsServer) {
                $dnsServerList[] = $this->profileConfig->wRangeFour($this->nodeNumber)->firstHost();

                continue;
            }
            if ('@GW6@' === $configDnsServer) {
                $dnsServerList[] = $this->profileConfig->wRangeSix($this->nodeNumber)->firstHost();

                continue;
            }
            $dnsServerList[] = $configDnsServer;
        }
        // add DNS domains, @see wg-quick(8)
        if (null !== $dnsDomain = $this->profileConfig->dnsDomain()) {
            $dnsServerList[] = $dnsDomain;
        }
        // add DNS search domains, @see wg-quick(8)
        $dnsServerList = array_merge($dnsServerList, $this->profileConfig->dnsDomainSearch());

        return array_unique($dnsServerList);
    }
}
