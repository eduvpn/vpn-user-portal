<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\WireGuard;

use LC\Portal\IP;
use LC\Portal\ProfileConfig;

/**
 * Represent a WireGuard client configuration file.
 */
class WgClientConfig
{
    private ProfileConfig $profileConfig;
    private ?string $privateKey;
    private string $ipFour;
    private string $ipSix;
    private string $serverPublicKey;
    private int $wgPort;

    public function __construct(ProfileConfig $profileConfig, ?string $privateKey, string $ipFour, string $ipSix, string $serverPublicKey, int $wgPort)
    {
        $this->profileConfig = $profileConfig;
        $this->privateKey = $privateKey;
        $this->ipFour = $ipFour;
        $this->ipSix = $ipSix;
        $this->serverPublicKey = $serverPublicKey;
        $this->wgPort = $wgPort;
    }

    public function __toString(): string
    {
        $routeList = [];
        if ($this->profileConfig->defaultGateway()) {
            $routeList[] = '0.0.0.0/0';
            $routeList[] = '::/0';
        }
        $routeList = array_merge($routeList, $this->profileConfig->routes());

        $output = [];
        $output[] = '[Interface]';
        if (null !== $this->privateKey) {
            $output[] = 'PrivateKey = '.$this->privateKey;
        }
        $ipFour = IP::fromIpPrefix($this->profileConfig->range());
        $ipSix = IP::fromIpPrefix($this->profileConfig->range6());

        $output[] = 'Address = '.$this->ipFour.'/'.$ipFour->prefix().', '.$this->ipSix.'/'.$ipSix->prefix();
        if (0 !== \count($this->profileConfig->dns())) {
            $output[] = 'DNS = '.implode(', ', $this->dns());
        }
        $output[] = '';
        $output[] = '[Peer]';
        $output[] = 'PublicKey = '.$this->serverPublicKey;
        $output[] = 'AllowedIPs = '.implode(', ', $routeList);
        $output[] = 'Endpoint = '.$this->profileConfig->hostName().':'.(string) $this->wgPort;

        return implode(PHP_EOL, $output);
    }

    /**
     * @return array<string>
     */
    private function dns(): array
    {
        $dnsServerList = [];
        foreach ($this->profileConfig->dns() as $configDnsServer) {
            if ('@GW4@' === $configDnsServer) {
                $dnsServerList[] = IP::fromIpPrefix($this->profileConfig->range())->firstHost();

                continue;
            }
            if ('@GW6@' === $configDnsServer) {
                $dnsServerList[] = IP::fromIpPrefix($this->profileConfig->range6())->firstHost();

                continue;
            }
            $dnsServerList[] = $configDnsServer;
        }

        // add DNS search domains, @see wg-quick(8)
        return array_merge($dnsServerList, $this->profileConfig->dnsDomainSearch());
    }
}