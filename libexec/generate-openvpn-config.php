<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Portal\CA\EasyRsaCa;
use LC\Portal\Config\PortalConfig;
use LC\Portal\FileIO;
use LC\Portal\OpenVpn\ServerConfig;
use LC\Portal\OpenVpn\TlsCrypt;
use LC\Portal\Storage;

try {
    $configDir = sprintf('%s/config', $baseDir);
    $dataDir = sprintf('%s/data', $baseDir);
    $vpnConfigDir = sprintf('%s/openvpn-config', $baseDir);

    /** @var bool */
    $forceDev = false;
    foreach ($argv as $arg) {
        if ('--dev' === $arg) {
            $forceDev = true;
        }
    }

    /** @var int|null */
    $osType = null;
    if (FileIO::exists('/etc/redhat-release')) {
        $osType = ServerConfig::OS_REDHAT;
    }
    if (FileIO::exists('/etc/debian_version')) {
        $osType = ServerConfig::OS_DEBIAN;
    }
    if (null === $osType || $forceDev) {
        $osType = ServerConfig::OS_DEV;
    }

    $portalConfig = PortalConfig::fromFile(sprintf('%s/config.php', $configDir));
    $storage = new Storage(new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)), sprintf('%s/schema', $baseDir));
    $easyRsaCa = new EasyRsaCa(sprintf('%s/easy-rsa', $baseDir), sprintf('%s/easy-rsa', $dataDir));
    $tlsCrypt = TlsCrypt::fromFile(sprintf('%s/tls-crypt.key', $dataDir));
    $openVpn = new ServerConfig($portalConfig, $easyRsaCa, $tlsCrypt, $osType);
    $configList = $openVpn->getConfigList();
    foreach ($configList as $configName => $configFile) {
        $configFileName = sprintf('%s/%s.conf', $vpnConfigDir, $configName);
        echo 'Writing: '.basename($configFileName).PHP_EOL;
        FileIO::writeFile($configFileName, $configFile);
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
