<?php

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

    // auto detect user/group to use for OpenVPN process
    if (FileIO::exists('/etc/redhat-release')) {
        // RHEL/CentOS/Fedora
        echo 'OS Detected: RHEL/CentOS/Fedora...'.PHP_EOL;
        $libExecDir = '/usr/libexec/vpn-server-node';
        $vpnUser = 'openvpn';
        $vpnGroup = 'openvpn';
    } elseif (FileIO::exists('/etc/debian_version')) {
        // Debian/Ubuntu
        echo 'OS Detected: Debian/Ubuntu...'.PHP_EOL;
        $libExecDir = '/usr/lib/vpn-server-node';
        $vpnUser = 'nobody';
        $vpnGroup = 'nogroup';
    } else {
        echo 'OS Detected: Unknown...'.PHP_EOL;
        // we will no specify user/group and point to "../libexec" as the
        // libexec dir so we can run the OpenVPN processes in "development
        // mode" as well
        $libExecDir = null;
        $vpnUser = null;
        $vpnGroup = null;
    }

    $portalConfig = PortalConfig::fromFile(sprintf('%s/config.php', $configDir));
    $storage = new Storage(new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)), sprintf('%s/schema', $baseDir));
    $easyRsaCa = new EasyRsaCa(sprintf('%s/easy-rsa', $baseDir), sprintf('%s/easy-rsa', $dataDir));
    $tlsCrypt = TlsCrypt::fromFile(sprintf('%s/tls-crypt.key', $configDir));
    $openVpn = new ServerConfig($portalConfig, $easyRsaCa, $tlsCrypt, $libExecDir, $vpnUser, $vpnGroup);
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
