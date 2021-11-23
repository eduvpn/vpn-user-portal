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

    public function oSupport(): bool
    {
        return \in_array('openvpn', $this->protoList(), true);
    }

    public function wSupport(): bool
    {
        return \in_array('wireguard', $this->protoList(), true);
    }

    /**
     * @return array<string>
     */
    public function protoList(): array
    {
        return $this->requireStringArray('protoList', ['openvpn', 'wireguard']);
    }

    public function preferredProto(): string
    {
        $protoList = $this->protoList();
        if (null !== $preferredProto = $this->optionalString('preferredProto')) {
            if (!\in_array($preferredProto, $protoList, true)) {
                throw new ConfigException('"preferredProto" is not listed under "protoList"');
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

    public function oRangeFour(int $nodeNumber): IP
    {
        $oRangeFourList = $this->requireStringOrStringArray('oRangeFour');
        if ($nodeNumber >= \count($oRangeFourList)) {
            throw new ConfigException('"oRangeFour" for node "'.$nodeNumber.'" not set');
        }

        return IP::fromIpPrefix($oRangeFourList[$nodeNumber]);
    }

    public function oRangeSix(int $nodeNumber): IP
    {
        $oRangeSixList = $this->requireStringOrStringArray('oRangeSix');
        if ($nodeNumber >= \count($oRangeSixList)) {
            throw new ConfigException('"oRangeSix" for node "'.$nodeNumber.'" not set');
        }

        return IP::fromIpPrefix($oRangeSixList[$nodeNumber]);
    }

    public function wRangeFour(int $nodeNumber): IP
    {
        $wRangeFourList = $this->requireStringOrStringArray('wRangeFour');
        if ($nodeNumber >= \count($wRangeFourList)) {
            throw new ConfigException('"wRangeFour" for node "'.$nodeNumber.'" not set');
        }

        return IP::fromIpPrefix($wRangeFourList[$nodeNumber]);
    }

    public function wRangeSix(int $nodeNumber): IP
    {
        $wRangeSixList = $this->requireStringOrStringArray('wRangeSix');
        if ($nodeNumber >= \count($wRangeSixList)) {
            throw new ConfigException('"wRangeSix" for node "'.$nodeNumber.'" not set');
        }

        return IP::fromIpPrefix($wRangeSixList[$nodeNumber]);
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
     * @return array<string>
     */
    public function dnsServerList(): array
    {
        return $this->requireStringArray('dnsServerList', []);
    }

    /**
     * OpenVPN only.
     */
    public function clientToClient(): bool
    {
        return $this->requireBool('clientToClient', false);
    }

    /**
     * OpenVPN only.
     */
    public function enableLog(): bool
    {
        return $this->requireBool('enableLog', false);
    }

    /**
     * @return ?array<string>
     */
    public function aclPermissionList(): ?array
    {
        return $this->optionalStringArray('aclPermissionList');
    }

    public function nodeUrl(int $nodeNumber): string
    {
        $nodeUrlList = $this->requireStringOrStringArray('nodeUrl', ['http://localhost:41194']);
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
    public function oUdpPortList(): array
    {
        return $this->requireIntArray('oUdpPortList', [1194]);
    }

    /**
     * OpenVPN only.
     *
     * @return array<int>
     */
    public function oTcpPortList(): array
    {
        return $this->requireIntArray('oTcpPortList', [1194]);
    }

    /**
     * OpenVPN only.
     *
     * @return array<int>
     */
    public function oExposedUdpPortList(): array
    {
        return $this->requireIntArray('oExposedUdpPortList', []);
    }

    /**
     * OpenVPN only.
     *
     * @return array<int>
     */
    public function oExposedTcpPortList(): array
    {
        return $this->requireIntArray('oExposedTcpPortList', []);
    }

    /**
     * OpenVPN only.
     */
    public function blockLan(): bool
    {
        return $this->requireBool('blockLan', false);
    }

    public function dnsDomain(): ?string
    {
        return $this->optionalString('dnsDomain');
    }

    /**
     * Additional DNS search domains. Will include "dnsDomain" as well, if set.
     *
     * @return array<string>
     */
    public function dnsDomainSearch(): array
    {
        $dnsDomainSearch = $this->requireStringArray('dnsDomainSearch', []);
        if (null !== $dnsDomain = $this->dnsDomain()) {
            $dnsDomainSearch[] = $dnsDomain;
        }

        return array_unique($dnsDomainSearch);
    }
}
