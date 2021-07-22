<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Portal\Config;
use LC\Portal\IP;

/*
 * We want to generate forward and reverse DNS zones for all VPN profiles. But
 * as we can use multiple OpenVPN processes it is not that simple. We have
 * both IPv4 and IPv6 to deal with. We want to give all clients the same
 * forward and reverse DNS, both on IPv4 and IPv6. We do not want DNS entries
 * for "network" and "broadcast" IPv4 addresses.
 */

try {
    // ask for the domain name for the (reverse) DNS entries
    $systemHostName = gethostname();
    echo sprintf('(Reverse) Domain [%s]: ', $systemHostName);
    $domainName = trim(fgets(\STDIN));
    if (empty($domainName)) {
        $domainName = $systemHostName;
    }

    $config = Config::fromFile($baseDir.'/config/config.php');

    $forwardDns = [];
    $reverseFour = [];
    $reverseSix = [];
    foreach ($config->profileConfigList() as $profileConfig) {
        $rangeFour = $profileConfig->range();
        $rangeSix = $profileConfig->range6();
        $splitCount = count($profileConfig->vpnProtoPorts());
        $ipFour = IP::fromIpPrefix($rangeFour);
        $ipSix = IP::fromIpPrefix($rangeSix);
        $ipFourSplit = $ipFour->split($splitCount);
        $ipSixSplit = $ipSix->split($splitCount);
        $gatewayNo = 1;
        $profileNumber = $profileConfig->profileNumber();
        for ($j = 0; $j < $splitCount; ++$j) {
            $noOfHosts = $ipFourSplit[$j]->numberOfHosts();
            $firstFourHost = $ipFourSplit[$j]->firstHost();
            $firstSixHost = $ipSixSplit[$j]->firstHost();
            $forwardDns[sprintf('gw-%03d-%03d', $profileNumber, $gatewayNo)] = ['ipFour' => $firstFourHost, 'ipSix' => $firstSixHost];
            $gwIpFourOrigin = implode('.', array_slice(array_reverse(explode('.', $firstFourHost)), 1, 3)).'.in-addr.arpa.';
            $gwIpSixOrigin = implode('.', str_split(strrev(substr(bin2hex(inet_pton($firstSixHost)), 0, 16)), 1)).'.ip6.arpa.';
            $reverseFour[$gwIpFourOrigin][$firstFourHost] = sprintf('gw-%03d-%03d.%s.', $profileNumber, $gatewayNo, $domainName);
            $reverseSix[$gwIpSixOrigin][$firstSixHost] = sprintf('gw-%03d-%03d.%s.', $profileNumber, $gatewayNo, $domainName);
            $longFourIp = ip2long($firstFourHost);
            $longSixIp = inet_pton($firstSixHost);
            for ($i = 0; $i < $noOfHosts - 1; ++$i) {
                $clientFourIp = long2ip($longFourIp + $i + 1);
                $sixStart = pack('n', 4096 + $i);
                $clientSixIp = inet_ntop(substr($longSixIp, 0, 14).$sixStart);
                $ipFourOrigin = implode('.', array_slice(array_reverse(explode('.', $clientFourIp)), 1, 3)).'.in-addr.arpa.';
                $ipSixOrigin = implode('.', str_split(strrev(substr(bin2hex($longSixIp), 0, 16)), 1)).'.ip6.arpa.';
                $reverseFour[$ipFourOrigin][$clientFourIp] = sprintf('c-%03d-%03d-%03d.%s.', $profileNumber, $gatewayNo, $i + 1, $domainName);
                $reverseSix[$ipSixOrigin][$clientSixIp] = sprintf('c-%03d-%03d-%03d.%s.', $profileNumber, $gatewayNo, $i + 1, $domainName);
                $forwardDns[sprintf('c-%03d-%03d-%03d', $profileNumber, $gatewayNo, $i + 1)] = ['ipFour' => $clientFourIp, 'ipSix' => $clientSixIp];
            }
            ++$gatewayNo;
        }
    }

    echo '###############'.\PHP_EOL;
    echo '# FORWARD DNS #'.\PHP_EOL;
    echo '###############'.\PHP_EOL;
    echo sprintf('$ORIGIN %s.', $domainName).\PHP_EOL;
    foreach ($forwardDns as $hostName => $ipList) {
        echo sprintf('%-20s', $hostName);
        echo sprintf('IN A    %s', $ipList['ipFour']).\PHP_EOL;
        echo sprintf('%20sIN AAAA %s', '', $ipList['ipSix']).\PHP_EOL;
    }

    echo '####################'.\PHP_EOL;
    echo '# REVERSE IPv4 DNS #'.\PHP_EOL;
    echo '####################'.\PHP_EOL;
    foreach ($reverseFour as $ipFourOrigin => $ipEntryList) {
        echo sprintf('$ORIGIN %s', $ipFourOrigin).\PHP_EOL;
        foreach ($ipEntryList as $ipEntry => $hostName) {
            $ipLast = explode('.', $ipEntry)[3];
            echo sprintf('%-8s IN PTR %s', $ipLast, $hostName).\PHP_EOL;
        }
    }

    echo '####################'.\PHP_EOL;
    echo '# REVERSE IPv6 DNS #'.\PHP_EOL;
    echo '####################'.\PHP_EOL;
    foreach ($reverseSix as $ipSixOrigin => $ipEntryList) {
        echo sprintf('$ORIGIN %s', $ipSixOrigin).\PHP_EOL;
        foreach ($ipEntryList as $ipEntry => $hostName) {
            $ipLast = implode('.', str_split(strrev(substr(bin2hex(inet_pton($ipEntry)), 16)), 1));
            echo sprintf('%s IN PTR %s', $ipLast, $hostName).\PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;
    exit(1);
}
