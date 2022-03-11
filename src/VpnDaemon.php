<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\Cfg\Config;
use Vpn\Portal\HttpClient\Exception\HttpClientException;
use Vpn\Portal\HttpClient\HttpClientInterface;
use Vpn\Portal\HttpClient\HttpClientRequest;

/**
 * Class interfacing with vpn-daemon and preparing the response data to be
 * easier to use from PHP. Also implements a simple cache for the wPeerList and
 * oConnectionList to prevent the need to query the same node multiple times in
 * case of multi profile setups.
 */
class VpnDaemon
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * @return null|array{load_average:array<float>,cpu_count:int}
     */
    public function nodeInfo(string $nodeUrl): ?array
    {
        try {
            return Json::decode(
                $this->httpClient->send(
                    new HttpClientRequest('GET', $nodeUrl.'/i/node')
                )->body()
            );
        } catch (HttpClientException $e) {
            $this->logger->error((string) $e);

            return null;
        }
    }

    /**
     * @return array<string,array{public_key:string,ip_net:array<string>,last_handshake_time:?string,bytes_in:int,bytes_out:int}>
     */
    public function wPeerList(string $nodeUrl, bool $showAll): array
    {
        try {
            $wPeerList = Json::decode(
                $this->httpClient->send(
                    new HttpClientRequest('GET', $nodeUrl.'/w/peer_list', ['show_all' => $showAll ? 'yes' : 'no'])
                )->body()
            );

            $pList = [];
            foreach ($wPeerList['peer_list'] as $peerInfo) {
                $pList[$peerInfo['public_key']] = $peerInfo;
            }

            return $pList;
        } catch (HttpClientException $e) {
            $this->logger->error((string) $e);

            return [];
        }
    }

    public function wPeerAdd(string $nodeUrl, string $publicKey, string $ipFour, string $ipSix): void
    {
        try {
            $this->httpClient->send(
                new HttpClientRequest(
                    'POST',
                    $nodeUrl.'/w/add_peer',
                    [],
                    [
                        'public_key' => $publicKey,
                        'ip_net' => [$ipFour.'/32', $ipSix.'/128'],
                    ]
                )
            );
        } catch (HttpClientException $e) {
            $this->logger->error((string) $e);
        }
    }

    /**
     * @return ?array{public_key:string,ip_net:array<string>,last_handshake_time:?string,bytes_in:int,bytes_out:int}
     */
    public function wPeerRemove(string $nodeUrl, string $publicKey): ?array
    {
        try {
            return Json::decode(
                $this->httpClient->send(
                    new HttpClientRequest(
                        'POST',
                        $nodeUrl.'/w/remove_peer',
                        [],
                        [
                            'public_key' => $publicKey,
                        ]
                    )
                )->body()
            );
        } catch (HttpClientException $e) {
            $this->logger->error((string) $e);

            return null;
        }
    }

    /**
     * @return array<string,array{common_name:string,ip_four:string,ip_six:string}>
     */
    public function oConnectionList(string $nodeUrl): array
    {
        try {
            $oConnectionList = Json::decode(
                $this->httpClient->send(
                    new HttpClientRequest(
                        'GET',
                        $nodeUrl.'/o/connection_list'
                    )
                )->body()
            );

            $cList = [];
            foreach ($oConnectionList['connection_list'] as $clientInfo) {
                $cList[$clientInfo['common_name']] = $clientInfo;
            }

            return $cList;
        } catch (HttpClientException $e) {
            $this->logger->error((string) $e);

            return [];
        }
    }

    public function oDisconnectClient(string $nodeUrl, string $commonName): void
    {
        try {
            $this->httpClient->send(
                new HttpClientRequest(
                    'POST',
                    $nodeUrl.'/o/disconnect_client',
                    [],
                    [
                        'common_name' => $commonName,
                    ]
                )
            );
        } catch (HttpClientException $e) {
            $this->logger->error((string) $e);
        }
    }

    /**
     * This method is responsible for three things:
     * 1. (Re)add WireGuard peers when they are missing, e.g. after a node reboot
     * 2. Delete WireGuard peers with expired configurations
     * 3. Disconnect OpenVPN clients with expired certificates.
     *
     * It will first figure out which peers/clients should be there and
     * remove/disconnect the ones that should NOT be there (anymore). It will
     * then add the WG peers that should (still) be there.
     *
     * Due to the architecture, e.g. multiple profiles can use the same vpn-daemon,
     * profiles can have multiple vpn-daemons and the vpn-daemon has no concept of
     * "profiles" the administration is a bit complicated...
     */
    public function sync(Config $config, Storage $storage): void
    {
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
                    foreach ($this->wPeerList($nodeUrl, true) as $publicKey => $wPeerInfo) {
                        if (!\array_key_exists($publicKey, $wPeerListInDatabase)) {
                            //echo sprintf('**REMOVE** [%s]: %s', $nodeUrl, $publicKey).PHP_EOL;
                            //XXX we MUST make sure the IP info also matches, otherwise delete it as well
                            $this->wPeerRemove(
                                $nodeUrl,
                                $publicKey
                            );
                            // XXX should we *continue* here? otherwise it still gets added to wPeerList...
                        }
                        $wPeerList[$publicKey] = $wPeerInfo;
                    }
                }
                if ($profileConfig->oSupport()) {
                    foreach (array_keys($this->oConnectionList($nodeUrl)) as $commonName) {
                        if (!\array_key_exists($commonName, $oCertListInDatabase)) {
                            //echo sprintf('**DISCONNECT** [%s]: %s', $nodeUrl, $commonName).PHP_EOL;
                            $this->oDisconnectClient(
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
        $wgPeersToAdd = array_diff(array_keys($wPeerListInDatabase), array_keys($wPeerList));
        foreach ($wgPeersToAdd as $publicKey) {
            // based on the publicKey we can now find the profile + node
            $peerInfo = $wPeerListInDatabase[$publicKey];
            $profileId = $peerInfo['profile_id'];
            $ipFour = $peerInfo['ip_four'];
            $ipSix = $peerInfo['ip_six'];
            $nodeNumber = $peerInfo['node_number'];
            $nodeUrl = $config->profileConfig($profileId)->nodeUrl($nodeNumber);

            //echo sprintf('**ADD** [%s]: %s (%s,%s)', $nodeUrl, $publicKey, $ipFour, $ipSix).PHP_EOL;
            $this->wPeerAdd(
                $nodeUrl,
                $publicKey,
                $ipFour,
                $ipSix
            );
        }
    }
}
