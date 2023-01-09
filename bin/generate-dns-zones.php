<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use Vpn\Portal\Cfg\Config;
use Vpn\Portal\DnsZoneGenerator;

function showHelp(): void
{
    echo '  --forward'.PHP_EOL;
    echo '        Include only forward DNS instead of both forward and reverse DNS'.PHP_EOL;
    echo '  --reverse'.PHP_EOL;
    echo '        Include only reverse DNS instead of both forward and reverse DNS'.PHP_EOL;
    echo '  -4'.PHP_EOL;
    echo '        Include only IPv4 entries instead of both IPv4 and IPv6'.PHP_EOL;
    echo '  -6'.PHP_EOL;
    echo '        Include only IPv6 entries instead of both IPv4 and IPv6'.PHP_EOL;
    echo PHP_EOL;
}

try {
    $forwardDns = true;
    $reverseDns = true;
    $ipProto = DnsZoneGenerator::IP_BOTH;

    foreach ($argv as $arg) {
        if ('--forward' === $arg) {
            $forwardDns = true;
            $reverseDns = false;
            continue;
        }
        if ('--reverse' === $arg) {
            $forwardDns = false;
            $reverseDns = true;
            continue;
        }
        if ('-4' === $arg) {
            $ipProto = DnsZoneGenerator::IP_FOUR;
            continue;
        }
        if ('-6' === $arg) {
            $ipProto = DnsZoneGenerator::IP_SIX;
            continue;
        }
        if ('-h' === $arg || '--help' === $arg) {
            showHelp();
            exit(0);
        }
    }

    // ask for the domain name for the (reverse) DNS entries
    $systemHostName = gethostname();
    echo sprintf('(Reverse) Domain [%s]: ', $systemHostName);
    $domainName = trim(fgets(\STDIN));
    if (empty($domainName)) {
        $domainName = $systemHostName;
    }

    $config = Config::fromFile($baseDir.'/config/config.php');
    if ($forwardDns) {
        echo DnsZoneGenerator::forwardDns($domainName, $config, $ipProto);
    }
    if ($reverseDns) {
        echo DnsZoneGenerator::reverseDns($domainName, $config, $ipProto);
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
