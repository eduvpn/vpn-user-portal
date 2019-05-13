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
use LC\Portal\Node\LocalNodeApi;
use LC\Portal\Node\ServerConfig;
use LC\Portal\Storage;
use LC\Portal\TlsCrypt;

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
        throw new RuntimeException('only RHEL/CentOS/Fedora or Debian/Ubuntu supported');
    }

    $portalConfig = PortalConfig::fromFile(sprintf('%s/config.php', $configDir));
    $storage = new Storage(new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)), sprintf('%s/schema', $baseDir));
    $easyRsaCa = new EasyRsaCa(sprintf('%s/easy-rsa', $baseDir), sprintf('%s/easy-rsa', $dataDir));
    $tlsCrypt = new TlsCrypt($dataDir);
    $localNodeApi = new LocalNodeApi($easyRsaCa, $tlsCrypt, $portalConfig, $storage);
    $openVpn = new ServerConfig($localNodeApi, $vpnConfigDir, $libExecDir, $vpnUser, $vpnGroup);
    $openVpn->writeProfiles();
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
