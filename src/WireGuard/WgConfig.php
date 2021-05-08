<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\WireGuard;

/**
 * Represent a WireGuard client configuration file.
 */
class WgConfig
{
    private string $publicKey;
    private string $ipFour;
    private string $ipSix;
    private string $serverPublicKey;
    private string $hostName;
    private int $listenPort;

    /** @var array<string> */
    private array $dnsServerList;
    private ?string $privateKey;

    /**
     * @param array<string> $dnsServerList
     */
    public function __construct(string $publicKey, string $ipFour, string $ipSix, string $serverPublicKey, string $hostName, int $listenPort, array $dnsServerList, ?string $privateKey)
    {
        $this->publicKey = $publicKey;
        $this->ipFour = $ipFour;
        $this->ipSix = $ipSix;
        $this->serverPublicKey = $serverPublicKey;
        $this->hostName = $hostName;
        $this->listenPort = $listenPort;
        $this->dnsServerList = $dnsServerList;
        $this->privateKey = $privateKey;
    }

    public function __toString(): string
    {
        $output = [];
        $output[] = '[Interface]';
        if (null !== $this->privateKey) {
            $output[] = 'PrivateKey = '.$this->privateKey;
        }
        $output[] = 'Address = '.$this->ipFour.'/24, '.$this->ipSix.'/64';
        if (0 !== \count($this->dnsServerList)) {
            $output[] = 'DNS = '.implode(', ', $this->dnsServerList);
        }
        $output[] = '';
        $output[] = '[Peer]';
        $output[] = 'PublicKey = '.$this->serverPublicKey;
        $output[] = 'AllowedIPs = 0.0.0.0/0, ::/0';
        $output[] = 'Endpoint = '.$this->hostName.':'.(string) $this->listenPort;
        // client is probably behind NAT, so try to keep the connection alive
        $output[] = 'PersistentKeepalive = 25';

        return implode(\PHP_EOL, $output);
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    public function setPrivateKey(string $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    public function getIpFour(): string
    {
        return $this->ipFour;
    }

    public function getIpSix(): string
    {
        return $this->ipSix;
    }
}
