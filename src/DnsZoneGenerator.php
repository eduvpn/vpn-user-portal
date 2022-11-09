<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\Cfg\Config;

/**
 * Generate forward/reverse DNS zones for a VPN profile.
 */
class DnsZoneGenerator
{
    public const IP_FOUR = 1;
    public const IP_SIX = 2;
    public const IP_BOTH = 3;

    public static function forwardDns(string $domainName, Config $config, int $ipProto): string
    {
        $output = '$ORIGIN '.$domainName.'.'.PHP_EOL;
        foreach (self::generateMapping($config) as $hostName => $clientIpList) {
            [$ipFour, $ipSix] = $clientIpList;
            if (self::IP_FOUR === $ipProto) {
                $output .= sprintf("%-20s IN A    %s\n", $hostName, $ipFour);
            }
            if (self::IP_SIX === $ipProto) {
                $output .= sprintf("%-20s IN AAAA %s\n", $hostName, $ipSix);
            }
            if (self::IP_BOTH === $ipProto) {
                $output .= sprintf("%-20s IN A    %s\n                     IN AAAA %s\n", $hostName, $ipFour, $ipSix);
            }
        }

        return $output;
    }

    public static function reverseDns(string $domainName, Config $config, int $ipProto): string
    {
        $reverseFour = [];
        $reverseSix = [];

        // first we map all IPs from all profiles from all nodes to a name,
        // just like with forward DNS and then put them in /64 (IPv4) and /24
        // (IPv4) "buckets" as we want to generate reverse zones for those
        // network prefixes as that seems the easiest for admins to copy into
        // their (reverse) DNS software configuration.
        foreach (self::generateMapping($config) as $hostName => $clientIpList) {
            [$ipFour, $ipSix] = $clientIpList;
            $ipFourOrigin = implode('.', array_slice(array_reverse(explode('.', $ipFour)), 1, 3)).'.in-addr.arpa.';
            $ipSixOrigin = implode('.', str_split(strrev(substr(bin2hex(inet_pton($ipSix)), 0, 16)), 1)).'.ip6.arpa.';
            $reverseFour[$ipFourOrigin][$ipFour] = sprintf('%s.%s.', $hostName, $domainName);
            $reverseSix[$ipSixOrigin][$ipSix] = sprintf('%s.%s.', $hostName, $domainName);
        }

        $output = '';

        // IPv4
        if (self::IP_FOUR === $ipProto || self::IP_BOTH === $ipProto) {
            foreach ($reverseFour as $ipFourOrigin => $ipEntryList) {
                asort($ipEntryList, SORT_NATURAL);
                $output.= sprintf('$ORIGIN %s', $ipFourOrigin).\PHP_EOL;
                foreach ($ipEntryList as $ipEntry => $hostName) {
                    $ipLast = explode('.', $ipEntry)[3];
                    $output.= sprintf('%-8s IN PTR %s', $ipLast, $hostName).\PHP_EOL;
                }
            }
        }

        // IPv6
        if (self::IP_SIX === $ipProto || self::IP_BOTH === $ipProto) {
            foreach ($reverseSix as $ipSixOrigin => $ipEntryList) {
                asort($ipEntryList, SORT_NATURAL);
                $output.= sprintf('$ORIGIN %s', $ipSixOrigin).\PHP_EOL;
                foreach ($ipEntryList as $ipEntry => $hostName) {
                    $ipLast = implode('.', str_split(strrev(substr(bin2hex(inet_pton($ipEntry)), 16)), 1));
                    $output.= sprintf('%s IN PTR %s', $ipLast, $hostName).\PHP_EOL;
                }
            }
        }

        return $output;
    }

    /**
     * Generate name to IP mapping.
     * @return array<string, array{string,string}>
     */
    public static function generateMapping(Config $config): array
    {
        $hostNameClientIpListMapping = [];
        foreach ($config->profileConfigList() as $profileConfig) {
            foreach ($profileConfig->onNode() as $nodeNumber) {
                if ($profileConfig->oSupport()) {
                    $hostNameClientIpListMapping = array_merge(
                        $hostNameClientIpListMapping,
                        self::forOpenvpn(
                            $profileConfig->oRangeFour($nodeNumber),
                            $profileConfig->oRangeSix($nodeNumber),
                            count($profileConfig->oUdpPortList())+count($profileConfig->oTcpPortList())
                        ),
                    );
                }

                if ($profileConfig->wSupport()) {
                    $hostNameClientIpListMapping = array_merge(
                        $hostNameClientIpListMapping,
                        self::forWireGuard(
                            $profileConfig->wRangeFour($nodeNumber),
                            $profileConfig->wRangeSix($nodeNumber)
                        )
                    );
                }
            }
        }

        return $hostNameClientIpListMapping;
    }

    /**
     * Create a mapping from IP to number for all IPs assigned to VPN clients.
     * This is more tricky with OpenVPN, because there are multiple processes
     * over which the IP prefix is split. Not all IPs in the prefix are used
     * for clients, some are used for the "gateway" and some are unused
     * because of network/broadcast IPs. With IPv6 it is slightly simpler. We
     * MUST make sure that the number assigned to the IPv4 address is the
     * same number assigned to IPv6 address belonging to the same connection.
     *
     * @return array<string, array{string,string}>
     */
    private static function forOpenvpn(Ip $ipFourPrefix, Ip $ipSixPrefix, int $networkCount): array
    {
        $clientIpList = [];
        $splitIpFourPrefixList = $ipFourPrefix->split($networkCount);
        $splitIpSixPrefixList = $ipSixPrefix->split($networkCount);
        foreach ($splitIpFourPrefixList as $k => $splitIpFourPrefix) {
            $splitIpFourClientIpList = $splitIpFourPrefix->clientIpListFour();
            $oIpSixPrefix = $splitIpSixPrefixList[$k];
            $oIpSixClientList = $oIpSixPrefix->clientIpListSix(count($splitIpFourClientIpList));
            foreach ($splitIpFourClientIpList as $i => $splistIpFourClientIp) {
                $clientIpList[self::ipToName($splistIpFourClientIp)] = [$splistIpFourClientIp, $oIpSixClientList[$i]];
            }
        }

        return $clientIpList;
    }

    /**
     * @return array<array{string,string}>
     */
    private static function forWireGuard(Ip $ipFourPrefix, Ip $ipSixPrefix): array
    {
        $clientIpList = [];
        $ipFourClientIpList = $ipFourPrefix->clientIpListFour();
        $ipSixClientIpList = $ipSixPrefix->clientIpListSix(count($ipFourClientIpList));
        foreach ($ipFourClientIpList as $i => $ipFourClientIp) {
            $clientIpList[self::ipToName($ipFourClientIp)] = [$ipFourClientIp, $ipSixClientIpList[$i]];
        }

        return $clientIpList;
    }

    private static function ipToName(string $ipAddress): string
    {
        return 'c-'.str_replace('.', '-', $ipAddress);
    }
}
