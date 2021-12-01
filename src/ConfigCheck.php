<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

class ConfigCheck
{
    /**
     * @return array<string,array<string>>
     */
    public static function verify(Config $config): array
    {
        $issueList = [];
        $usedRangeList = [];

        foreach ($config->profileConfigList() as $profileConfig) {
            $profileProblemList = [];

            self::verifyDefaultGatewayHasDnsServerList($profileConfig, $profileProblemList);
            self::verifyRangeOverlap($profileConfig, $usedRangeList, $profileProblemList);
            self::verifyRoutesAndExcludeRoutesAreNormalized($profileConfig, $profileProblemList);
            self::verifyDnsRouteIsPushedWhenNotDefaultGateway($profileConfig, $profileProblemList);

            $issueList[$profileConfig->profileId()] = $profileProblemList;
        }

        // check OpenVPN port overlap (per node)
        // make sure IP space is big enough for OpenVPN/WireGuard
        // verify that when DNS servers are pushed to VPN client that they are also routed over the VPN

        return $issueList;
    }

    private static function verifyRoutesAndExcludeRoutesAreNormalized(ProfileConfig $profileConfig, array &$profileProblemList): void
    {
        foreach ($profileConfig->routeList() as $routeIpPrefix) {
            $ip = IP::fromIpPrefix($routeIpPrefix);
            if (!$ip->equals($ip->network())) {
                $profileProblemList[] = sprintf('route "%s" is not normalized, expecting "%s"', (string) $ip, (string) $ip->network());
            }
        }

        foreach ($profileConfig->excludeRouteList() as $routeIpPrefix) {
            $ip = IP::fromIpPrefix($routeIpPrefix);
            if (!$ip->equals($ip->network())) {
                $profileProblemList[] = sprintf('excluded route "%s" is not normalized, expecting "%s"', (string) $ip, (string) $ip->network());
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

        foreach ($profileConfig->dnsServerList() as $dnsServer) {
            $dnsServerIp = IP::fromIp($dnsServer);
            foreach ($profileConfig->routeList() as $routeIpPrefix) {
                $routeIp = IP::fromIpPrefix($routeIpPrefix);
                if ($routeIp->contains($dnsServerIp)) {
                    continue;
                }

                $profileProblemList[] = sprintf('traffic to DNS server "%s" not routed over VPN', $dnsServerIp->address());
            }
        }
    }

    /**
     * @param array<string> $profileProblemList
     */
    private static function verifyDefaultGatewayHasDnsServerList(ProfileConfig $profileConfig, array &$profileProblemList): void
    {
        if ($profileConfig->defaultGateway() && 0 === \count($profileConfig->dnsServerList())) {
            $profileProblemList[] = 'default gateway enabled, but no DNS servers configured';
        }
    }

    /**
     * @param array<IP>     $usedRangeList
     * @param array<string> $profileProblemList
     */
    private static function verifyRangeOverlap(ProfileConfig $profileConfig, array &$usedRangeList, array &$profileProblemList): void
    {
        // perhaps we can also log the profile to usedRangeList so it is easier
        // to find offending ranges, but string search in config file should
        // work as well...
        $profileRangeList = [];
        for ($n = 0; $n < $profileConfig->nodeCount(); ++$n) {
            if ($profileConfig->oSupport()) {
                $profileRangeList[] = $profileConfig->oRangeFour($n);
                $profileRangeList[] = $profileConfig->oRangeSix($n);
            }
            if ($profileConfig->wSupport()) {
                $profileRangeList[] = $profileConfig->wRangeFour($n);
                $profileRangeList[] = $profileConfig->wRangeSix($n);
            }
        }

        foreach ($profileRangeList as $profileRange) {
            foreach ($usedRangeList as $usedRange) {
                if ($profileRange->contains($usedRange) || $usedRange->contains($profileRange)) {
                    $profileProblemList[] = sprintf('range "%s" overlaps with range "%s"', (string) $profileRange, (string) $usedRange);
                }
            }
            $usedRangeList[] = $profileRange;
        }
    }
}
