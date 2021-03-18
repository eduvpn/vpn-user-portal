<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

/*
 * Generate example IPv4 and IPv6 address ranges for VPN clients.
 *
 * IPv4:
 * Random value for the second and third octet, e.g: 10.53.129.0/24
 *
 * IPv6:
 * The IPv6 address is generated according to RFC 4193 (Global ID), it results
 * in a /64 network.
 */

$showFamily = [4, 6];
for ($i = 1; $i < $argc; ++$i) {
    if ('-6' === $argv[$i]) {
        $showFamily = [6];
    }
    if ('-4' === $argv[$i]) {
        $showFamily = [4];
    }
    if ('--help' === $argv[$i]) {
        echo 'SYNTAX: '.$argv[0].' [-4] [-6]'.\PHP_EOL;
        exit(0);
    }
}

if (in_array(4, $showFamily, true)) {
    $ipFourPrefix = sprintf(
    '10.%s.%s.0/24',
        hexdec(bin2hex(random_bytes(1))),
        hexdec(bin2hex(random_bytes(1)))
    );
    echo $ipFourPrefix.\PHP_EOL;
}

if (in_array(6, $showFamily, true)) {
    $ipSixPrefix = sprintf(
        'fd%s:%s:%s:%s::/64',
        bin2hex(random_bytes(1)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2))
    );
    echo $ipSixPrefix.\PHP_EOL;
}
