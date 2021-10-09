<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use RuntimeException;

/**
 * Detect OpenVPN Server Configuration.
 * XXX fix it!
 */
class ServerConfigCheck
{
//    /**
//     * @param array<ProfileConfig> $profileConfigList
//     */
//    public static function verify(array $profileConfigList): void
//    {
//        $listenProtoPortList = [];
//        $rangeList = [];
//        foreach ($profileConfigList as $profileConfig) {
//            // make sure DNS is set when defaultGateway is true
//            if ($profileConfig->defaultGateway() && 0 === \count($profileConfig->dns())) {
//                throw new RuntimeException(sprintf('no DNS set for profile "%s", but defaultGateway is true', $profileConfig->profileId()));
//            }

//            // make sure the listen/port/proto is unique
//            $listenAddress = $profileConfig->listenIp();
//            $vpnProtoPorts = $profileConfig->vpnProtoPorts();
//            foreach ($vpnProtoPorts as $vpnProtoPort) {
//                $listenProtoPort = $listenAddress.' -> '.$vpnProtoPort;
//                if (\in_array($listenProtoPort, $listenProtoPortList, true)) {
//                    throw new RuntimeException(sprintf('"listen/vpnProtoPorts combination "%s" in profile "%s" already used before', $listenProtoPort, $profileConfig->profileId()));
//                }
//                $listenProtoPortList[] = $listenProtoPort;
//            }

//            // network bits required for all processes
//            $prefixSpace = log(\count($vpnProtoPorts), 2);

//            // make sure "range" is 29 or lower for each OpenVPN process
//            // (OpenVPN server limitation)
//            $rangeFour = $profileConfig->range();
//            [$ipRange, $ipPrefix] = explode('/', $rangeFour);
//            if ((int) $ipPrefix > (29 - $prefixSpace)) {
//                throw new RuntimeException(sprintf('"range" in profile "%s" MUST be at least "/%d" to accommodate %d OpenVPN server process(es)', $profileConfig->profileId(), 29 - $prefixSpace, \count($vpnProtoPorts)));
//            }
//            $rangeList[] = $rangeFour;

//            // make sure "range6" is 112 or lower for each OpenVPN process
//            // (OpenVPN server limitation)
//            $rangeSix = $profileConfig->range6();
//            [$ipRange, $ipPrefix] = explode('/', $rangeSix);
//            // we ALSO want the prefix to be divisible by 4 (restriction in
//            // IP.php)
//            if (0 !== ((int) $ipPrefix) % 4) {
//                throw new RuntimeException(sprintf('prefix length of "range6" in profile "%s" MUST be divisible by 4', $profileConfig->profileId(), $ipPrefix));
//            }
//            if ((int) $ipPrefix > (112 - $prefixSpace)) {
//                throw new RuntimeException(sprintf('"range6" in profile "%s" MUST be at least "/%d" to accommodate %d OpenVPN server process(es)', $profileConfig->profileId(), 112 - $prefixSpace, \count($vpnProtoPorts)));
//            }
//            $rangeList[] = $rangeSix;
//        }

//        // Check for IPv4/IPv6 range overlaps between profiles
//        $overlapList = self::checkOverlap($rangeList);
//        if (0 !== \count($overlapList)) {
//            foreach ($overlapList as $o) {
//                echo sprintf('WARNING: IP range %s overlaps with IP range %s', $o[0], $o[1]).PHP_EOL;
//            }
//        }
//    }

//    /**
//     * Check whether any of the provided IP ranges in IP/prefix notation
//     * overlaps any of the others.
//     *
//     * @param array<string> $ipRangeList
//     *
//     * @return array<array{0:string, 1:string}>
//     */
//    public static function checkOverlap(array $ipRangeList): array
//    {
//        $overlapList = [];
//        $minMaxFourList = [];
//        $minMaxSixList = [];
//        foreach ($ipRangeList as $ipRange) {
//            if (false === strpos($ipRange, ':')) {
//                // IPv4
//                self::getMinMax($minMaxFourList, $overlapList, $ipRange);
//            } else {
//                // IPv6
//                self::getMinMax($minMaxSixList, $overlapList, $ipRange);
//            }
//        }

//        return $overlapList;
//    }

//    private static function getMinMax(array &$minMaxList, array &$overlapList, string $ipRange): void
//    {
//        [$ipAddress, $ipPrefix] = explode('/', $ipRange);
//        $binIp = self::ipToBin($ipAddress);
//        $minIp = Binary::safeSubstr($binIp, 0, (int) $ipPrefix).str_repeat('0', Binary::safeStrlen($binIp) - (int) $ipPrefix);
//        $maxIp = Binary::safeSubstr($binIp, 0, (int) $ipPrefix).str_repeat('1', Binary::safeStrlen($binIp) - (int) $ipPrefix);
//        foreach ($minMaxList as $minMax) {
//            if ($minIp >= $minMax[0] && $minIp <= $minMax[1]) {
//                $overlapList[] = [$ipRange, $minMax[2]];

//                continue;
//            }
//            if ($maxIp >= $minMax[0] && $maxIp <= $minMax[1]) {
//                $overlapList[] = [$ipRange, $minMax[2]];

//                continue;
//            }
//        }
//        $minMaxList[] = [$minIp, $maxIp, $ipRange];
//    }

//    /**
//     * Convert an IP address to its binary representation to make it easy to
//     * do string operations on the strings of length 32 (IPv4) and 128 (IPv6)
//     * regarding determining the first/last host of the prefix to be able to do
//     * string compare for overlap detection. This is quite ugly :-).
//     */
//    private static function ipToBin(string $ipAddr): string
//    {
//        $hexStr = bin2hex(inet_pton($ipAddr));
//        $binStr = '';
//        // base_convert does not work with arbitrary length input, so here we
//        // limit it to 32 bits
//        for ($i = 0; $i < Binary::safeStrlen($hexStr) / 8; ++$i) {
//            $binStr .= str_pad(
//                base_convert(
//                    Binary::safeSubstr($hexStr, $i * 8, 8),
//                    16,
//                    2
//                ),
//                32,
//                '0',
//                STR_PAD_LEFT
//            );
//        }

//        return $binStr;
//    }
}
