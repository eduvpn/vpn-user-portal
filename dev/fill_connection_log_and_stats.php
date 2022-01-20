<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

/*
 * This script can be used for testing the statistics functionality with any
 * database. As housekeeping only moves statistics to the aggregate table after
 * a week, it helps to be able to simulate this by adding some data for the
 * last two weeks. It runs quite slow to import all the data, but works well!
 */

use Vpn\Portal\Config;
use Vpn\Portal\Dt;
use Vpn\Portal\Storage;

$config = Config::fromFile($baseDir.'/config/config.php');
$storage = new Storage($config->dbConfig($baseDir));

$dateTime = Dt::get();
$loopDateTime = Dt::get('now -2 weeks', new DateTimeZone('UTC'));

$startTimestamp = $loopDateTime->getTimestamp();
$endTimestamp = time();

$randomNumber = 0;
while ($loopDateTime < $dateTime) {
    foreach ($config->profileConfigList() as $profileConfig) {
        $upperBound = random_int(10, 100);
        $randomNumber += random_int(-5, 5);
        if ($randomNumber > $upperBound || $randomNumber < 0) {
            do {
                $randomNumber += random_int(-5, 5);
            } while ($randomNumber > $upperBound || $randomNumber < 0);
        }

        $storage->statsAdd($loopDateTime, $profileConfig->profileId(), $randomNumber);
    }
    $loopDateTime = $loopDateTime->add(new DateInterval('PT5M'));
}

foreach ($config->profileConfigList() as $profileConfig) {
    for ($i = 0; $i < 100; ++$i) {
        $userNo = (string) random_int(0, 50);
        $conId = base64_encode(random_bytes(32));
        $conStart = random_int($startTimestamp, $endTimestamp);
        do {
            $conEnd = random_int($startTimestamp, $endTimestamp);
        } while ($conEnd <= $conStart);

        // connect
        $storage->clientConnect(
            'user'.$userNo,
            $profileConfig->profileId(),
            1 === random_int(0, 1) ? 'openvpn' : 'wireguard',
            $conId,
            '10.10.10.'.$userNo,
            'fd10::'.$userNo,
            Dt::get('@'.(string) $conStart)
        );

        // disconnect
        $storage->clientDisconnect(
            'user'.$userNo,
            $profileConfig->profileId(),
            $conId,
            random_int(0, 2 ** 13),
            random_int(0, 2 ** 13),
            Dt::get('@'.(string) $conEnd)
        );
    }
}
