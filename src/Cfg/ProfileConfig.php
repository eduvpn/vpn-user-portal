<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Cfg;

use Vpn\Portal\Cfg\Exception\ConfigException;
use Vpn\Portal\Ip;

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

    public function displayName(): string
    {
        return $this->requireString('displayName');
    }

    public function hostName(int $nodeNumber): string
    {
        $hostNameList = $this->requireStringOrStringArray('hostName');
        if ($nodeNumber >= \count($hostNameList)) {
            throw new ConfigException('"hostName" for node "'.$nodeNumber.'" not set');
        }

        return $hostNameList[$nodeNumber];
    }

    public function defaultGateway(): bool
    {
        return $this->requireBool('defaultGateway', true);
    }

    /**
     * @return array<string>
     */
    public function dnsServerList(): array
    {
        return $this->requireStringArray('dnsServerList', []);
    }

    /**
     * @return array<string>
     */
    public function routeList(): array
    {
        return $this->requireStringArray('routeList', []);
    }

    /**
     * @return array<string>
     */
    public function excludeRouteList(): array
    {
        return $this->requireStringArray('excludeRouteList', []);
    }

    /**
     * @return ?array<string>
     */
    public function aclPermissionList(): ?array
    {
        return $this->optionalStringArray('aclPermissionList');
    }

    /**
     * @return array<string>
     */
    public function dnsSearchDomainList(): array
    {
        return array_unique($this->requireStringArray('dnsSearchDomainList', []));
    }

    public function nodeUrl(int $nodeNumber): string
    {
        $nodeUrlList = $this->requireStringOrStringArray('nodeUrl', ['http://localhost:41194']);
        if ($nodeNumber >= \count($nodeUrlList)) {
            throw new ConfigException('"nodeUrl" for node "'.$nodeNumber.'" not set');
        }

        return $nodeUrlList[$nodeNumber];
    }

    public function preferredProto(): string
    {
        $protoList = $this->protoList();
        if (null !== $preferredProto = $this->optionalString('preferredProto')) {
            if (!\in_array($preferredProto, $protoList, true)) {
                throw new ConfigException('profile does not support "preferredProto"');
            }

            return $preferredProto;
        }

        // if only one protocol is supported, that is the default
        if (1 === \count($protoList)) {
            return $protoList[0];
        }

        // default to OpenVPN (for now) if admin did not set
        // "vpnProtoPreferred" and we support both protocols
        return 'openvpn';
    }

    public function wRangeFour(int $nodeNumber): Ip
    {
        $wRangeFourList = $this->requireStringOrStringArray('wRangeFour');
        if ($nodeNumber >= \count($wRangeFourList)) {
            throw new ConfigException('"wRangeFour" for node "'.$nodeNumber.'" not set');
        }

        return Ip::fromIpPrefix($wRangeFourList[$nodeNumber]);
    }

    public function wRangeSix(int $nodeNumber): Ip
    {
        $wRangeSixList = $this->requireStringOrStringArray('wRangeSix');
        if ($nodeNumber >= \count($wRangeSixList)) {
            throw new ConfigException('"wRangeSix" for node "'.$nodeNumber.'" not set');
        }

        return Ip::fromIpPrefix($wRangeSixList[$nodeNumber]);
    }

    public function oRangeFour(int $nodeNumber): Ip
    {
        $oRangeFourList = $this->requireStringOrStringArray('oRangeFour');
        if ($nodeNumber >= \count($oRangeFourList)) {
            throw new ConfigException('"oRangeFour" for node "'.$nodeNumber.'" not set');
        }

        return Ip::fromIpPrefix($oRangeFourList[$nodeNumber]);
    }

    public function oRangeSix(int $nodeNumber): Ip
    {
        $oRangeSixList = $this->requireStringOrStringArray('oRangeSix');
        if ($nodeNumber >= \count($oRangeSixList)) {
            throw new ConfigException('"oRangeSix" for node "'.$nodeNumber.'" not set');
        }

        return Ip::fromIpPrefix($oRangeSixList[$nodeNumber]);
    }

    /**
     * @return array<int>
     */
    public function oUdpPortList(): array
    {
        return $this->requireIntArray('oUdpPortList', [1194]);
    }

    /**
     * @return array<int>
     */
    public function oTcpPortList(): array
    {
        return $this->requireIntArray('oTcpPortList', [1194]);
    }

    /**
     * @return array<int>
     */
    public function oExposedUdpPortList(): array
    {
        return $this->requireIntArray('oExposedUdpPortList', []);
    }

    /**
     * @return array<int>
     */
    public function oExposedTcpPortList(): array
    {
        return $this->requireIntArray('oExposedTcpPortList', []);
    }

    public function oBlockLan(): bool
    {
        return $this->requireBool('oBlockLan', false);
    }

    public function oEnableLog(): bool
    {
        return $this->requireBool('oEnableLog', false);
    }

    public function oListenOn(): Ip
    {
        return Ip::fromIp($this->requireString('oListenOn', '::'));
    }

    /**
     * @return array<string>
     */
    public function protoList(): array
    {
        $protoList = [];
        if ($this->oSupport()) {
            $protoList[] = 'openvpn';
        }
        if ($this->wSupport()) {
            $protoList[] = 'wireguard';
        }

        return $protoList;
    }

    public function oSupport(): bool
    {
        return null !== $this->optionalStringOrStringArray('oRangeFour') && null !== $this->optionalStringOrStringArray('oRangeSix');
    }

    public function wSupport(): bool
    {
        return null !== $this->optionalStringOrStringArray('wRangeFour') && null !== $this->optionalStringOrStringArray('wRangeSix');
    }

    public function nodeCount(): int
    {
        return \count($this->requireStringOrStringArray('nodeUrl', ['http://127.0.0.1:41194']));
    }
}
