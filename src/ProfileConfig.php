<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\Exception\ConfigException;

class ProfileConfig
{
    use ConfigTrait;

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    public function profileId(): string
    {
        return $this->requireString('profileId');
    }

    public function vpnProto(): string
    {
        return $this->requireString('vpnProto');
    }

    public function nodeCount(): int
    {
        return \count($this->requireStringOrStringArray('nodeUrl', ['http://127.0.0.1:41194']));
    }

    public function hostName(int $nodeNumber): string
    {
        $hostNameList = $this->requireStringOrStringArray('hostName');
        if ($nodeNumber >= \count($hostNameList)) {
            throw new ConfigException('"hostName" for node "'.$nodeNumber.'" not set');
        }

        return $hostNameList[$nodeNumber];
    }

    public function rangeFour(int $nodeNumber): IP
    {
        $rangeFourList = $this->requireStringOrStringArray('rangeFour');
        if ($nodeNumber >= \count($rangeFourList)) {
            throw new ConfigException('"rangeFour" for node "'.$nodeNumber.'" not set');
        }

        return IP::fromIpPrefix($rangeFourList[$nodeNumber]);
    }

    public function rangeSix(int $nodeNumber): IP
    {
        $rangeSixList = $this->requireStringOrStringArray('rangeSix');
        if ($nodeNumber >= \count($rangeSixList)) {
            throw new ConfigException('"rangeSix" for node "'.$nodeNumber.'" not set');
        }

        return IP::fromIpPrefix($rangeSixList[$nodeNumber]);
    }

    public function displayName(): string
    {
        return $this->requireString('displayName');
    }

    public function defaultGateway(): bool
    {
        return $this->requireBool('defaultGateway', true);
    }

    /**
     * @return array<string>
     */
    public function tunnelRouteList(): array
    {
        return $this->requireStringArray('tunnelRouteList', []);
    }

    /**
     * @return array<string>
     */
    public function dnsServerList(): array
    {
        return $this->requireStringArray('dnsServerList', []);
    }

    public function clientToClient(): bool
    {
        return $this->requireBool('clientToClient', false);
    }

    /**
     * OpenVPN only.
     */
    public function enableLog(): bool
    {
        if ('wireguard' === $this->vpnProto()) {
            throw new ConfigException('"enableLog" is only used for OpenVPN');
        }

        return $this->requireBool('enableLog', false);
    }

    public function enableAcl(): bool
    {
        return $this->requireBool('enableAcl', false);
    }

    /**
     * @return array<string>
     */
    public function aclPermissionList(): array
    {
        return $this->requireStringArray('aclPermissionList', []);
    }

    public function nodeUrl(int $nodeNumber): string
    {
        $nodeUrlList = $this->requireStringOrStringArray('nodeUrl', ['http://127.0.0.1:41194']);
        if ($nodeNumber >= \count($nodeUrlList)) {
            throw new ConfigException('"nodeUrl" for node "'.$nodeNumber.'" not set');
        }

        return $nodeUrlList[$nodeNumber];
    }

    /**
     * OpenVPN only.
     *
     * @return array<int>
     */
    public function udpPortList(): array
    {
        if ('wireguard' === $this->vpnProto()) {
            throw new ConfigException('"udpPortList" is only used for OpenVPN');
        }

        return $this->requireIntArray('udpPortList', [1194]);
    }

    /**
     * OpenVPN only.
     *
     * @return array<int>
     */
    public function tcpPortList(): array
    {
        if ('wireguard' === $this->vpnProto()) {
            throw new ConfigException('"tcpPortList" is only used for OpenVPN');
        }

        return $this->requireIntArray('tcpPortList', [1194]);
    }

    /**
     * OpenVPN only.
     *
     * @return array<int>
     */
    public function exposedUdpPortList(): array
    {
        if ('wireguard' === $this->vpnProto()) {
            throw new ConfigException('"exposedUdpPortList" is only used for OpenVPN');
        }

        return $this->requireIntArray('exposedUdpPortList', []);
    }

    /**
     * OpenVPN only.
     *
     * @return array<int>
     */
    public function exposedTcpPortList(): array
    {
        if ('wireguard' === $this->vpnProto()) {
            throw new ConfigException('"exposedTcpPortList" is only used for OpenVPN');
        }

        return $this->requireIntArray('exposedTcpPortList', []);
    }

    /**
     * OpenVPN only.
     */
    public function blockLan(): bool
    {
        if ('wireguard' === $this->vpnProto()) {
            throw new ConfigException('"blockLan" is only used for OpenVPN');
        }

        return $this->requireBool('blockLan', false);
    }

    public function dnsDomain(): ?string
    {
        return $this->optionalString('dnsDomain');
    }

    /**
     * @return array<string>
     */
    public function dnsDomainSearch(): array
    {
        return $this->requireStringArray('dnsDomainSearch', []);
    }
}
