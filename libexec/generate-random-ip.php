<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

$v4 = sprintf(
    '10.%s.%s.0/25',
    hexdec(bin2hex(random_bytes(1))),
    hexdec(bin2hex(random_bytes(1)))
);

$v6 = sprintf(
    'fd%s:%s:%s:%s::/64',
    bin2hex(random_bytes(1)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2))
);

echo sprintf('IPv4 CIDR  : %s', $v4).PHP_EOL;
echo sprintf('IPv6 prefix: %s', $v6).PHP_EOL;
