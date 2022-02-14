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

use Vpn\Portal\Cfg\Config;
use Vpn\Portal\Cfg\ProfileConfig;
use Vpn\Portal\HttpClient\CurlHttpClient;
use Vpn\Portal\Ip;
use Vpn\Portal\Storage;
use Vpn\Portal\SysLogger;
use Vpn\Portal\VpnDaemon;

/*
 * This script is responsible for three things:
 * 1. (Re)add WireGuard peers when they are missing, e.g. after a node reboot
 * 2. Delete WireGuard peers with expired configurations
 * 3. Disconnect OpenVPN clients with expired certificates
 *
 * This script interfaces with `vpn-daemon` running on the node(s). It will
 * first figure out which peers/clients should be there and remove/disconnect
 * the ones that should NOT be there (anymore). It will then add the WG peers
 * that should (still) be there.
 *
 * Due to the architecture, e.g. multiple profiles can use the same vpn-daemon,
 * profiles can have multiple vpn-daemons and the vpn-daemon has no concept of
 * "profiles" the administration is a bit complicated...
 */

$logger = new SysLogger('vpn-user-portal');

/**
 * Determine which nodeUrl this WireGuard peer should be registered at. This
 * is super inefficient!
 * XXX clean this up!
 */
function determineNodeUrl(ProfileConfig $profileConfig, Ip $ipFour): ?string
{
    for ($i = 0; $i <= $profileConfig->nodeCount(); ++$i) {
        $wRangeFour = $profileConfig->wRangeFour($i);
        if (in_array($ipFour->address(), $wRangeFour->clientIpListFour(), true)) {
            return $profileConfig->nodeUrl($i);
        }
    }

    // unable to find nodeUrl because the provided IP is not serviced by any
    // of the nodes of this profile...
    return null;
}

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage($config->dbConfig($baseDir));
    $vpnDaemon = new VpnDaemon(new CurlHttpClient($baseDir.'/config/keys/vpn-daemon'), $logger);

    // Obtain a list of all WireGuard/OpenVPN peers/clients that we have in the
    // database
    $wPeerListInDatabase = [];
    $oCertListInDatabase = [];
    foreach ($config->profileConfigList() as $profileConfig) {
        if ($profileConfig->wSupport()) {
            $wPeerListInDatabase = array_merge($wPeerListInDatabase, $storage->wPeerListByProfileId($profileConfig->profileId(), Storage::EXCLUDE_EXPIRED | Storage::EXCLUDE_DISABLED_USER));
        }
        if ($profileConfig->oSupport()) {
            $oCertListInDatabase = array_merge($oCertListInDatabase, $storage->oCertListByProfileId($profileConfig->profileId(), Storage::EXCLUDE_EXPIRED));
        }
    }

    // Remove/Disconnect WireGuard/OpenVPN peers/client that we no longer have
    // in our database (or are expired) and obtain a list of *configured*
    // WireGuard peers in the node(s)
    $wPeerList = [];
    foreach ($config->profileConfigList() as $profileConfig) {
        for ($i = 0; $i < $profileConfig->nodeCount(); ++$i) {
            $nodeUrl = $profileConfig->nodeUrl($i);
            if ($profileConfig->wSupport()) {
                // if the peer does not exist in the database, remove it...
                foreach ($vpnDaemon->wPeerList($nodeUrl, true) as $publicKey => $wPeerInfo) {
                    if (!array_key_exists($publicKey, $wPeerListInDatabase)) {
                        //echo sprintf('**REMOVE** [%s]: %s', $nodeUrl, $publicKey).PHP_EOL;
                        //XXX we MUST make sure the IP info also matches, otherwise delete it as well
                        $vpnDaemon->wPeerRemove(
                            $nodeUrl,
                            $publicKey
                        );
                    }
                    $wPeerList[$publicKey] = $wPeerInfo;
                }
            }
            if ($profileConfig->oSupport()) {
                foreach (array_keys($vpnDaemon->oConnectionList($nodeUrl)) as $commonName) {
                    if (!array_key_exists($commonName, $oCertListInDatabase)) {
                        //echo sprintf('**DISCONNECT** [%s]: %s', $nodeUrl, $commonName).PHP_EOL;
                        $vpnDaemon->oDisconnectClient(
                            $nodeUrl,
                            $commonName
                        );
                    }
                }
            }
        }
    }

    // Register WireGuard peers we have in our database, but not in our node(s)
    // everything that is in wPeerListInDatabase, but not in wPeerList needs to be added to the appropriate node
    // XXX not sure this is the correct order or things, may have to flip the parameters
    $wgPeersToAdd = array_diff(array_keys($wPeerListInDatabase), array_keys($wPeerList));
    foreach ($wgPeersToAdd as $publicKey) {
        // based on the publicKey we can now find the profile + node
        $peerInfo = $wPeerListInDatabase[$publicKey];
        $profileId = $peerInfo['profile_id'];
        $ipFour = $peerInfo['ip_four'];
        $ipSix = $peerInfo['ip_six'];
        if (null === $nodeUrl = determineNodeUrl($config->profileConfig($profileId), Ip::fromIp($ipFour))) {
            continue;
        }
        //echo sprintf('**ADD** [%s]: %s (%s,%s)', $nodeUrl, $publicKey, $ipFour, $ipSix).PHP_EOL;
        $vpnDaemon->wPeerAdd(
            $nodeUrl,
            $publicKey,
            $ipFour,
            $ipSix
        );
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
