<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\Cfg\Config;
use Vpn\Portal\Cfg\ProfileConfig;

class ConfigCheck
{
    /**
     * @return array<string,array<string>>
     */
    public static function verify(Config $config): array
    {
        $issueList = [];
        $usedRangeList = [];
        $usedUdpPortList = [];
        $usedTcpPortList = [];
        $nodeProfileMapping = [];

        foreach ($config->profileConfigList() as $profileConfig) {
            $profileProblemList = [];

            self::verifyDefaultGatewayHasDnsServerList($profileConfig, $profileProblemList);
            self::verifyRangeOverlap($profileConfig, $usedRangeList, $profileProblemList);
            self::verifyRoutesAndExcludeRoutesAreNormalized($profileConfig, $profileProblemList);
            self::verifyDnsRouteIsPushedWhenNotDefaultGateway($profileConfig, $profileProblemList);
            self::verifyNonLocalNodeUrlHasTls($profileConfig, $profileProblemList);
            self::verifyUniqueOpenVpnPortsPerNode($profileConfig, $usedUdpPortList, $usedTcpPortList, $profileProblemList);
            self::verifyRouteListIsEmptyWithDefaultGateway($profileConfig, $profileProblemList);
            self::verifyNodeNumberUrlConsistency($profileConfig, $nodeProfileMapping, $profileProblemList);
            $issueList[$profileConfig->profileId()] = $profileProblemList;
        }

        // make sure IP space is big enough for OpenVPN/WireGuard

        return $issueList;
    }

    private static function verifyRouteListIsEmptyWithDefaultGateway(ProfileConfig $profileConfig, array &$profileProblemList): void
    {
        if (!$profileConfig->defaultGateway()) {
            return;
        }

        if (0 !== \count($profileConfig->routeList())) {
            $profileProblemList[] = '"defaultGateway" is "true", expecting "routeList" to be empty';
        }
    }

    private static function verifyUniqueOpenVpnPortsPerNode(ProfileConfig $profileConfig, array &$usedUdpPortList, array &$usedTcpPortList, array &$profileProblemList): void
    {
        if (!$profileConfig->oSupport()) {
            return;
        }

        // collect all ports per unique nodeUrl and report duplicate ones
        $udpPortList = $profileConfig->oUdpPortList();
        $tcpPortList = $profileConfig->oTcpPortList();
        foreach ($profileConfig->onNode() as $nodeNumber) {
            $nodeUrl = $profileConfig->nodeUrl($nodeNumber);
            if (!\array_key_exists($nodeUrl, $usedUdpPortList)) {
                $usedUdpPortList[$nodeUrl] = [];
            }
            if (!\array_key_exists($nodeUrl, $usedTcpPortList)) {
                $usedTcpPortList[$nodeUrl] = [];
            }

            foreach ($udpPortList as $udpPort) {
                if (\in_array($udpPort, $usedUdpPortList[$nodeUrl], true)) {
                    $profileProblemList[] = sprintf('Node "%s" already uses UDP port "%d"', $nodeUrl, $udpPort);

                    continue;
                }
                $usedUdpPortList[$nodeUrl][] = $udpPort;
            }
            foreach ($tcpPortList as $tcpPort) {
                if (\in_array($tcpPort, $usedTcpPortList[$nodeUrl], true)) {
                    $profileProblemList[] = sprintf('Node "%s" already uses TCP port "%d"', $nodeUrl, $tcpPort);

                    continue;
                }
                $usedTcpPortList[$nodeUrl][] = $tcpPort;
            }
        }
    }

    private static function verifyNonLocalNodeUrlHasTls(ProfileConfig $profileConfig, array &$profileProblemList): void
    {
        foreach ($profileConfig->onNode() as $nodeNumber) {
            $nodeUrl = $profileConfig->nodeUrl($nodeNumber);
            $nodeUrlScheme = parse_url($nodeUrl, PHP_URL_SCHEME);
            if ('https' === $nodeUrlScheme) {
                return;
            }
            $nodeUrlHost = parse_url($nodeUrl, PHP_URL_HOST);
            if (\in_array($nodeUrlHost, ['localhost', '127.0.0.1', '::1'], true)) {
                return;
            }

            $profileProblemList[] = sprintf('Node URL "%s" is not using https', $nodeUrl);
        }
    }

    private static function verifyRoutesAndExcludeRoutesAreNormalized(ProfileConfig $profileConfig, array &$profileProblemList): void
    {
        foreach ($profileConfig->routeList() as $routeIpPrefix) {
            $ip = Ip::fromIpPrefix($routeIpPrefix);
            if (!$ip->equals($ip->network())) {
                $profileProblemList[] = sprintf('"routeList" entry "%s" is not normalized, expecting "%s"', (string) $ip, (string) $ip->network());
            }
        }

        foreach ($profileConfig->excludeRouteList() as $routeIpPrefix) {
            $ip = Ip::fromIpPrefix($routeIpPrefix);
            if (!$ip->equals($ip->network())) {
                $profileProblemList[] = sprintf('"excludeRouteList" entry "%s" is not normalized, expecting "%s"', (string) $ip, (string) $ip->network());
            }
        }
    }

    private static function verifyDnsRouteIsPushedWhenNotDefaultGateway(ProfileConfig $profileConfig, array &$profileProblemList): void
    {
        if ($profileConfig->defaultGateway()) {
            return;
        }

        if (0 === \count($profileConfig->dnsServerList())) {
            return;
        }

        // if no DNS search domain is set in the scenario where the profile is
        // not the default gateway, DNS provided through VPN won't be used
        // anyway
        if (0 === count($profileConfig->dnsSearchDomainList())) {
            return;
        }

        foreach ($profileConfig->dnsServerList() as $dnsServer) {
            if (in_array($dnsServer, ['@GW4@', '@GW6@'], true)) {
                // the '@GW4@' and '@GW6@' templates are *always* "routed" over
                // the VPN
                continue;
            }
            $dnsServerIp = Ip::fromIp($dnsServer);
            foreach ($profileConfig->routeList() as $routeIpPrefix) {
                $routeIp = Ip::fromIpPrefix($routeIpPrefix);
                if ($routeIp->contains($dnsServerIp)) {
                    continue 2;
                }
            }

            $profileProblemList[] = sprintf('Traffic to DNS server "%s" will not be routed over VPN', $dnsServerIp->address());
        }
    }

    /**
     * @param array<string> $profileProblemList
     */
    private static function verifyDefaultGatewayHasDnsServerList(ProfileConfig $profileConfig, array &$profileProblemList): void
    {
        if ($profileConfig->defaultGateway() && 0 === \count($profileConfig->dnsServerList())) {
            $profileProblemList[] = '"defaultGateway" is "true", but "dnsServerList" is empty';
        }
    }

    /**
     * @param array<Ip>     $usedRangeList
     * @param array<string> $profileProblemList
     */
    private static function verifyRangeOverlap(ProfileConfig $profileConfig, array &$usedRangeList, array &$profileProblemList): void
    {
        // perhaps we can also log the profile to usedRangeList so it is easier
        // to find offending ranges, but string search in config file should
        // work as well...
        $profileRangeList = [];
        foreach ($profileConfig->onNode() as $nodeNumber) {
            if ($profileConfig->oSupport()) {
                $profileRangeList[] = $profileConfig->oRangeFour($nodeNumber);
                $profileRangeList[] = $profileConfig->oRangeSix($nodeNumber);
            }
            if ($profileConfig->wSupport()) {
                $profileRangeList[] = $profileConfig->wRangeFour($nodeNumber);
                $profileRangeList[] = $profileConfig->wRangeSix($nodeNumber);
            }
        }

        foreach ($profileRangeList as $profileRange) {
            foreach ($usedRangeList as $usedRange) {
                if ($profileRange->contains($usedRange) || $usedRange->contains($profileRange)) {
                    $profileProblemList[] = sprintf('Prefix "%s" is equal to, or overlaps prefix "%s"', (string) $profileRange, (string) $usedRange);
                }
            }
            $usedRangeList[] = $profileRange;
        }
    }

    /**
     * @param array<string,array<int, string>> $profileNodeNumberUrlListMapping
     * @param array<string> $profileProblemList
     */
    private static function verifyNodeNumberUrlConsistency(ProfileConfig $profileConfig, array &$profileNodeNumberUrlListMapping, array &$profileProblemList): void
    {
        // make sure onNode does not contain the same nodeNumber > 1
        if (array_values($profileConfig->onNode()) !== array_values(array_unique($profileConfig->onNode()))) {
            $profileProblemList[] = sprintf('onNode repeats nodeNumbers: [%s]', implode(',', $profileConfig->onNode()));

            return;
        }

        // verify nodeNumber(s) and nodeUrl(s)
        $nodeUrlList = [];
        foreach ($profileConfig->onNode() as $nodeNumber) {
            if ($nodeNumber < 0) {
                $profileProblemList[] = 'nodeNumber(s) MUST be >= 0';

                return;
            }
            $nodeUrl = $profileConfig->nodeUrl($nodeNumber);
            if (in_array($nodeUrl, $nodeUrlList, true)) {
                $profileProblemList[] = sprintf('duplidate nodeUrl "%s"', $nodeUrl);

                return;
            }
            $nodeUrlList[$nodeNumber] = $nodeUrl;
        }

        // make sure no other profile has our nodeUrl under a different nodeNumber
        foreach ($profileNodeNumberUrlListMapping as $profileId => $nodeNumberUrlList) {
            foreach ($nodeUrlList as $nodeNumber => $nodeUrl) {
                if (array_key_exists($nodeNumber, $nodeNumberUrlList)) {
                    if ($nodeNumberUrlList[$nodeNumber] !== $nodeUrl) {
                        $profileProblemList[] = sprintf('profile "%s" already defines nodeNumber "%d" with nodeUrl "%s"', $profileId, $nodeNumber, $nodeNumberUrlList[$nodeNumber]);
                    }
                }
            }
        }

        $profileNodeNumberUrlListMapping[$profileConfig->profileId()] = $nodeUrlList;
    }
}
