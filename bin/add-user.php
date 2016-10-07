#!/usr/bin/php
<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\CliParser;

try {
    $p = new CliParser(
        'Add a user to the portal',
        [
            'instance' => ['the instance to target, e.g. vpn.example', true, true],
            'user' => ['the username', true, true],
            'pass' => ['the password', true, true],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->e('help')) {
        echo $p->help();
        exit(0);
    }

    $configFile = sprintf('%s/config/%s/config.yaml', dirname(__DIR__), $opt->v('instance'));
    $config = Config::fromFile($configFile);
    $configData = $config->v();
    $passwordHash = password_hash($opt->v('pass'), PASSWORD_DEFAULT);
    $configData['FormAuthentication'][$opt->v('user')] = $passwordHash;
    Config::toFile($configFile, $configData, 0640);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
