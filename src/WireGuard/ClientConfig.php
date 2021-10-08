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
use LC\Portal\ProfileConfig;
use LC\Portal\QrCode;

/**
 * Represent a WireGuard client configuration file.
 */
class ClientConfig implements ClientConfigInterface
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

    public function contentType(): string
    {
        return 'application/x-wireguard-profile';
    }

    public function get(): string
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
        $output[] = 'Address = '.$this->ipFour.'/'.$this->profileConfig->range()->prefix().', '.$this->ipSix.'/'.$this->profileConfig->range6()->prefix();
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

    public function getQr(): string
    {
        return Base64::encode(QrCode::generate($this->get()));
    }

    /**
     * @return array<string>
     */
    private function dns(): array
    {
        $dnsServerList = [];
        foreach ($this->profileConfig->dns() as $configDnsServer) {
            if ('@GW4@' === $configDnsServer) {
                $dnsServerList[] = $this->profileConfig->range()->firstHost();

                continue;
            }
            if ('@GW6@' === $configDnsServer) {
                $dnsServerList[] = $this->profileConfig->range6()->firstHost();

                continue;
            }
            $dnsServerList[] = $configDnsServer;
        }

        // add DNS search domains, @see wg-quick(8)
        return array_merge($dnsServerList, $this->profileConfig->dnsDomainSearch());
    }
}
