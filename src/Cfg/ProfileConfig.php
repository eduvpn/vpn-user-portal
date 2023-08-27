<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
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
        $nodeIndex = $this->nodeNumberToIndex($nodeNumber);
        $hostNameList = $this->requireStringOrStringArray('hostName');
        if ($nodeIndex >= \count($hostNameList)) {
            throw new ConfigException('"hostName" for node "'.$nodeNumber.'" not set');
        }

        return $hostNameList[$nodeIndex];
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
        $nodeIndex = $this->nodeNumberToIndex($nodeNumber);
        $nodeUrlList = $this->requireStringOrStringArray('nodeUrl', ['http://localhost:41194']);
        if ($nodeIndex >= \count($nodeUrlList)) {
            throw new ConfigException('"nodeUrl" for node "'.$nodeNumber.'" not set');
        }

        return $nodeUrlList[$nodeIndex];
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
        $nodeIndex = $this->nodeNumberToIndex($nodeNumber);
        $wRangeFourList = $this->requireStringOrStringArray('wRangeFour');
        if ($nodeIndex >= \count($wRangeFourList)) {
            throw new ConfigException('"wRangeFour" for node "'.$nodeNumber.'" not set');
        }

        return Ip::fromIpPrefix($wRangeFourList[$nodeIndex]);
    }

    public function wRangeSix(int $nodeNumber): Ip
    {
        $nodeIndex = $this->nodeNumberToIndex($nodeNumber);
        $wRangeSixList = $this->requireStringOrStringArray('wRangeSix');
        if ($nodeIndex >= \count($wRangeSixList)) {
            throw new ConfigException('"wRangeSix" for node "'.$nodeNumber.'" not set');
        }

        return Ip::fromIpPrefix($wRangeSixList[$nodeIndex]);
    }

    public function oRangeFour(int $nodeNumber): Ip
    {
        $nodeIndex = $this->nodeNumberToIndex($nodeNumber);
        $oRangeFourList = $this->requireStringOrStringArray('oRangeFour');
        if ($nodeIndex >= \count($oRangeFourList)) {
            throw new ConfigException('"oRangeFour" for node "'.$nodeNumber.'" not set');
        }

        return Ip::fromIpPrefix($oRangeFourList[$nodeIndex]);
    }

    public function oRangeSix(int $nodeNumber): Ip
    {
        $nodeIndex = $this->nodeNumberToIndex($nodeNumber);
        $oRangeSixList = $this->requireStringOrStringArray('oRangeSix');
        if ($nodeIndex >= \count($oRangeSixList)) {
            throw new ConfigException('"oRangeSix" for node "'.$nodeNumber.'" not set');
        }

        return Ip::fromIpPrefix($oRangeSixList[$nodeIndex]);
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

    public function oListenOn(int $nodeNumber): Ip
    {
        if (null === $oListenOnList = $this->optionalStringOrStringArray('oListenOn')) {
            return Ip::fromIp('::');
        }
        $nodeIndex = $this->nodeNumberToIndex($nodeNumber);
        if ($nodeIndex >= \count($oListenOnList)) {
            throw new ConfigException('"oListenOn" for node "'.$nodeNumber.'" not set');
        }

        return Ip::fromIp($oListenOnList[$nodeIndex]);
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

    /**
     * Configuration option to allow specifying the "nodeNumber"(s) the VPN
     * profile is deployed on.
     *
     * @return array<int>
     */
    public function onNode(): array
    {
        return $this->requireIntOrIntArray('onNode', range(0, $this->nodeCount() - 1));
    }

    /**
     * Determine the maximum number of VPN clients that can connect to this
     * profile taking into consideration OpenVPN/WireGuard and the node(s).
     */
    public function maxClientLimit(): int
    {
        return $this->oMaxClientLimit() + $this->wMaxClientLimit();
    }

    public function oMaxClientLimit(): int
    {
        $maxClientLimit = 0;
        // OpenVPN can have multiple processes, that reduces the number of IP
        // addresses available for VPN clients...
        $oProcessCount = $this->oSupport() ? (count($this->oUdpPortList()) + count($this->oTcpPortList())) : 0;
        foreach ($this->onNode() as $nodeNumber) {
            if ($this->oSupport()) {
                $maxClientLimit += ((int) 2 ** (32 - $this->oRangeFour($nodeNumber)->prefix())) - 3 * $oProcessCount;
            }
        }

        return $maxClientLimit;
    }

    public function wMaxClientLimit(): int
    {
        $maxClientLimit = 0;
        foreach ($this->onNode() as $nodeNumber) {
            if ($this->wSupport()) {
                $maxClientLimit += ((int) 2 ** (32 - $this->wRangeFour($nodeNumber)->prefix())) - 3;
            }
        }

        return $maxClientLimit;
    }

    /**
     * Get a list of configuration keys NOT supported in order to warn the
     * admins on the "Info" page about it.
     *
     * @return array<string>
     */
    public function unsupportedConfigKeys(): array
    {
        $supportedKeys = [
            'defaultGateway',
            'displayName',
            'dnsSearchDomainList',
            'dnsServerList',
            'excludeRouteList',
            'hostName',
            'nodeUrl',
            'oBlockLan',
            'oEnableLog',
            'oExposedTcpPortList',
            'oExposedUdpPortList',
            'onNode',
            'oRangeFour',
            'oRangeSix',
            'oTcpPortList',
            'oUdpPortList',
            'profileId',
            'routeList',
            'wRangeFour',
            'wRangeSix',
            'aclPermissionList',
            'oListenOn',
            'oRangeFour',
            'preferredProto',
            'wRangeFour',
        ];

        $unsupportedKeys = [];
        foreach (array_keys($this->configData) as $configKey) {
            if (!in_array($configKey, $supportedKeys, true)) {
                $unsupportedKeys[] = $configKey;
            }
        }

        return $unsupportedKeys;
    }

    private function nodeCount(): int
    {
        return \count($this->requireStringOrStringArray('nodeUrl', ['http://127.0.0.1:41194']));
    }

    /**
     * Convert nodeNumber to nodeIndex. Each node has a unique "nodeNumber",
     * but as not all profiles necessarily are deployed on all nodes we need
     * to convert the nodeNumber to the index of the various profile
     * configuration options to find the "right" value for the node.
     *
     * For example: a VPN service has 4 nodes, and profile "employees" only
     * needs to be deployed on node 2 and 3. In the configuration the "onNode"
     * option MUST then be set to '[2, 3]'.
     * This allows us to keep configuration values like e.g. "wRangeSix"
     * 0-indexed and we don't need to set the key of the option to the node,
     * e.g.:
     *
     *     [2 => '10.10.10.10/24', 3 => '10.10.11.0/24']
     *
     * This is due to a bug that unfortuantely made it to the production
     * release and we can't break existing installations :-(
     *
     * @see https://todo.sr.ht/~eduvpn/server/90
     */
    private function nodeNumberToIndex(int $nodeNumber): int
    {
        $nodeIndex = array_search($nodeNumber, $this->onNode(), true);
        if (!\is_int($nodeIndex) || $nodeIndex < 0 || $nodeIndex > $this->nodeCount() - 1) {
            throw new ConfigException(sprintf('configuration for nodeNumber "%d" does not exist', $nodeNumber));
        }

        return $nodeIndex;
    }
}
