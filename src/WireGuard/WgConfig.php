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
class WgConfig
{
    private ProfileConfig $profileConfig;
    private string $publicKey;
    private ?string $privateKey;
    private string $ipFour;
    private string $ipSix;
    private string $serverPublicKey;

    public function __construct(ProfileConfig $profileConfig, string $publicKey, ?string $privateKey, string $ipFour, string $ipSix, string $serverPublicKey)
    {
        $this->profileConfig = $profileConfig;
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        $this->ipFour = $ipFour;
        $this->ipSix = $ipSix;
        $this->serverPublicKey = $serverPublicKey;
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
        // XXX what is up with /24 and /64? this needs to come from range/range6
        $ipFour = new IP($this->profileConfig->range());
        $ipSix = new IP($this->profileConfig->range6());

        $output[] = 'Address = '.$this->ipFour.$ipFour->getPrefix().', '.$this->ipSix.$ipSix->getPrefix();
        if (0 !== \count($this->profileConfig->dns())) {
            $output[] = 'DNS = '.implode(', ', $this->profileConfig->dns());
        }
        $output[] = '';
        $output[] = '[Peer]';
        $output[] = 'PublicKey = '.$this->serverPublicKey;
        $output[] = 'AllowedIPs = '.implode(', ', $routeList);
        $output[] = 'Endpoint = '.$this->profileConfig->hostName().':51820';

        return implode(\PHP_EOL, $output);
    }

    public function publicKey(): string
    {
        return $this->publicKey;
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
