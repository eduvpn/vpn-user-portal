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

function showHelp(array $argv)
{
    return implode(
        PHP_EOL,
        [
            sprintf('SYNTAX: %s [--instance domain.tld] --user username --pass password', $argv[0]),
            '',
            '--instance domain.tld      the instance to add the user to in case of multi',
            '                           instance deploy',
            '--user username            the user name',
            '--pass password            the plain text password',
            '',
        ]
    );
}

try {
    $instanceId = null;
    $userName = null;
    $userPass = null;

    for ($i = 1; $i < $argc; ++$i) {
        if ('--instance' === $argv[$i] || '-i' === $argv[$i]) {
            if (array_key_exists($i + 1, $argv)) {
                $instanceId = $argv[$i + 1];
                ++$i;
            }
        }
        if ('--user' === $argv[$i] || '-u' === $argv[$i]) {
            if (array_key_exists($i + 1, $argv)) {
                $userName = $argv[$i + 1];
                ++$i;
            }
        }
        if ('--pass' === $argv[$i] || '-p' === $argv[$i]) {
            if (array_key_exists($i + 1, $argv)) {
                $userPass = $argv[$i + 1];
                ++$i;
            }
        }
        if ('--help' === $argv[$i] || '-h' === $argv[$i]) {
            echo showHelp($argv);
            exit(0);
        }
    }

    if (is_null($userName) || is_null($userPass)) {
        throw new RuntimeException('--user and --pass must be specified, see --help');
    }

    if (is_null($instanceId)) {
        throw new RuntimeException('instance must be specified, see --help');
    }

    $configFile = sprintf('%s/config/%s/config.yaml', dirname(__DIR__), $instanceId);
    $config = Config::fromFile($configFile);
    $configData = $config->v();
    $passwordHash = password_hash($userPass, PASSWORD_DEFAULT);
    $configData['FormAuthentication'][$userName] = $passwordHash;
    Config::toFile($configFile, $configData);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
