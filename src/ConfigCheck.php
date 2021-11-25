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
     * @return array<string>
     */
    public static function verify(Config $config): array
    {
        $listOfProblems = [];
        $usedRangeList = [];

        foreach ($config->profileConfigList() as $profileConfig) {
            self::verifyDefaultGatewayHasDnsServerList($profileConfig, $listOfProblems);
            self::verifyRangeOverlap($profileConfig, $listOfProblems, $usedRangeList);
        }

        // check OpenVPN port overlap (per node)
        // make sure IP space is big enough for OpenVPN/WireGuard

        return $listOfProblems;
    }

    /**
     * @param array<string> $listOfProblems
     */
    private static function verifyDefaultGatewayHasDnsServerList(ProfileConfig $profileConfig, array &$listOfProblems): void
    {
        if ($profileConfig->defaultGateway() && 0 === \count($profileConfig->dnsServerList())) {
            $listOfProblems[] = sprintf('[%s]: default gateway enabled, but no DNS servers configured', $profileConfig->profileId());
        }
    }

    /**
     * @param array<string> $listOfProblems
     * @param array<IP>     $usedRangeList
     */
    private static function verifyRangeOverlap(ProfileConfig $profileConfig, array &$listOfProblems, array &$usedRangeList): void
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
                    $listOfProblems[] = sprintf('[%s]: range "%s" overlaps with range "%s"', $profileConfig->profileId(), (string) $profileRange, (string) $usedRange);
                }
            }
            $usedRangeList[] = $profileRange;
        }
    }
}
