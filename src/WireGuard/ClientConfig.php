<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\WireGuard;

use DateTimeImmutable;
use Vpn\Portal\Cfg\ProfileConfig;
use Vpn\Portal\ClientConfigInterface;
use Vpn\Portal\Exception\QrCodeException;
use Vpn\Portal\Ip;
use Vpn\Portal\IpNetList;
use Vpn\Portal\QrCode;

/**
 * Represent a WireGuard client configuration file.
 */
class ClientConfig implements ClientConfigInterface
{
    private string $portalUrl;
    private int $nodeNumber;
    private ProfileConfig $profileConfig;
    private ?string $privateKey = null;
    private string $ipFour;
    private string $ipSix;
    private string $serverPublicKey;
    private int $wgPort;
    private DateTimeImmutable $expiresAt;

    public function __construct(string $portalUrl, int $nodeNumber, ProfileConfig $profileConfig, string $ipFour, string $ipSix, string $serverPublicKey, int $wgPort, DateTimeImmutable $expiresAt)
    {
        $this->portalUrl = $portalUrl;
        $this->nodeNumber = $nodeNumber;
        $this->profileConfig = $profileConfig;
        $this->ipFour = $ipFour;
        $this->ipSix = $ipSix;
        $this->serverPublicKey = $serverPublicKey;
        $this->wgPort = $wgPort;
        $this->expiresAt = $expiresAt;
    }

    public function contentType(): string
    {
        return 'application/x-wireguard-profile';
    }

    public function setPrivateKey(string $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    public function get(): string
    {
        $ipFour = Ip::fromIp($this->ipFour, $this->profileConfig->wRangeFour($this->nodeNumber)->prefix());
        $ipSix = Ip::fromIp($this->ipSix, $this->profileConfig->wRangeSix($this->nodeNumber)->prefix());

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

        // we always want to add the gateway IP to the list of "AllowedIPs",
        // even if "routeList" is empty. Has no effect when "defaultGateway" is
        // is set to true as the gateway IPs are contained in `0.0.0.0/0` and
        // `::/0`
        $routeList->add(Ip::fromIp($ipFour->network()->firstHost()));
        $routeList->add(Ip::fromIp($ipSix->network()->firstHost()));

        $output = [
            sprintf('# Portal: %s', $this->portalUrl),
            sprintf('# Profile: %s (%s)', $this->profileConfig->displayName(), $this->profileConfig->profileId()),
            sprintf('# Expires: %s', $this->expiresAt->format(DateTimeImmutable::ATOM)),
            '',
        ];
        $output[] = '[Interface]';
        if (null !== $this->privateKey) {
            $output[] = 'PrivateKey = '.$this->privateKey;
        }
        $output[] = sprintf('Address = %s,%s', (string) $ipFour, (string) $ipSix);

        $dnsEntries = $this->getDns($this->profileConfig, $ipFour, $ipSix);
        if (0 !== \count($dnsEntries)) {
            $output[] = 'DNS = '.implode(',', $dnsEntries);
        }
        $output[] = '';
        $output[] = '[Peer]';
        $output[] = 'PublicKey = '.$this->serverPublicKey;
        $output[] = 'AllowedIPs = '.implode(',', $routeList->ls());
        $output[] = 'Endpoint = '.$this->profileConfig->hostName($this->nodeNumber).':'.(string) $this->wgPort;

        return implode("\n", $output);
    }

    public function getQr(): ?string
    {
        try {
            return QrCode::generate($this->get());
        } catch (QrCodeException $e) {
            return null;
        }
    }

    /**
     * @return array<string>
     */
    private static function getDns(ProfileConfig $profileConfig, Ip $ipFour, Ip $ipSix): array
    {
        $dnsServerList = [];
        foreach ($profileConfig->dnsServerList() as $dnsAddress) {
            if ('@GW4@' === $dnsAddress) {
                $dnsAddress = $ipFour->firstHost();
            }
            if ('@GW6@' === $dnsAddress) {
                $dnsAddress = $ipSix->firstHost();
            }
            $dnsServerList[] = $dnsAddress;
        }
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
