<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Common\Config;
use LC\Common\HttpClient\CurlHttpClient;
use LC\Portal\Federation\ForeignKeyListFetcher;

try {
    $configFile = sprintf('%s/config/config.php', $baseDir);
    $config = Config::fromFile($configFile);
    if ($config->s('Api')->requireBool('remoteAccess', false)) {
        $foreignKeyListFetcher = new ForeignKeyListFetcher($baseDir.'/data');
        $foreignKeyListFetcher->update(
            new CurlHttpClient(),
            'https://disco.eduvpn.org/v2/server_list.json',
            [
                'RWRtBSX1alxyGX+Xn3LuZnWUT0w//B6EmTJvgaAxBMYzlQeI+jdrO6KF', // fkooman@deic.dk, kolla@uninett.no
                'RWQKqtqvd0R7rUDp0rWzbtYPA3towPWcLDCl7eY9pBMMI/ohCmrS0WiM', // RoSp
            ]
        );
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).\PHP_EOL;
    exit(1);
}
