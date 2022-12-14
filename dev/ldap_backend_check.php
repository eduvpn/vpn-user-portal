<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once 'vendor/autoload.php';

use Vpn\Portal\Cfg\Config;
use Vpn\Portal\Http\Auth\LdapCredentialValidator;
use Vpn\Portal\NullLogger;

try {
    $baseDir = dirname(__DIR__);
    $config = Config::fromFile($baseDir.'/config/config.php');
    $l = new LdapCredentialValidator(
        $config->ldapAuthConfig(),
        new NullLogger()
    );

    if (2 !== $argc) {
        throw new Exception('specify User ID as parameter!');
    }

    if ($l->userExists($argv[1])) {
        echo 'User exists!'.PHP_EOL;

        exit(0);
    }
    echo 'User does NOT exist!'.PHP_EOL;
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).\PHP_EOL;

    exit(1);
}
