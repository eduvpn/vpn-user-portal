<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\WireGuard;

use LC\Portal\HttpClient\HttpClientInterface;
use LC\Portal\Json;

/**
 * Interface to the WireGuard Daemon (wg-daemon).
 */
class WgDaemon
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getPeers(string $wgDaemonEndpoint): array
    {
        $peerList = Json::decode($this->httpClient->get($wgDaemonEndpoint.'/w/peer_list', [], [])->getBody());

        return $peerList['peer_list'];
    }

    public function addPeer(string $wgDaemonEndpoint, string $publicKey, string $ipFour, string $ipSix): void
    {
        $rawPostData = implode(
            '&',
            [
                'public_key='.urlencode($publicKey),
                'ip_net='.urlencode($ipFour.'/32'),
                'ip_net='.urlencode($ipSix.'/128'),
            ]
        );

        $this->httpClient->postRaw(
            $wgDaemonEndpoint.'/w/add_peer',
            [],
            $rawPostData
        );
    }

    public function removePeer(string $wgDaemonEndpoint, string $publicKey): array
    {
        $rawPostData = implode('&', ['public_key='.urlencode($publicKey)]);
        $httpResponse = $this->httpClient->postRaw(
            $wgDaemonEndpoint.'/w/remove_peer',
            [],
            $rawPostData
        );

        return Json::decode($httpResponse->getBody());
    }

    /**
     * Very inefficient way to register all peers (again) with WG.
     */
    public function syncPeers(string $wgDaemonEndpoint, array $peerInfoList): void
    {
        // XXX this only adds peers, it may also needs to remove the ones that
        // shouldn't be there anymore. We need to implement a proper sync
        // together with wg-daemon...
        foreach ($peerInfoList as $peerInfo) {
            $this->addPeer($wgDaemonEndpoint, $peerInfo['public_key'], $peerInfo['ip_four'], $peerInfo['ip_six']);
        }
    }
}
