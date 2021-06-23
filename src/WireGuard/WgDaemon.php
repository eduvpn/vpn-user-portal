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
        $wgInfo = $this->getInfo($wgDaemonEndpoint);

        return \array_key_exists('Peers', $wgInfo) ? $wgInfo['Peers'] : [];
    }

    public function addPeer(string $wgDaemonEndpoint, string $publicKey, string $ipFour, string $ipSix): void
    {
        $wgInfo = $this->getInfo($wgDaemonEndpoint);
        $rawPostData = implode(
            '&',
            [
                'PublicKey='.urlencode($publicKey),
                'AllowedIPs='.urlencode($ipFour.'/32'),
                'AllowedIPs='.urlencode($ipSix.'/128'),
            ]
        );

        // XXX catch errors
        $httpResponse = $this->httpClient->postRaw(
            $wgDaemonEndpoint.'/add_peer',
            [],
            $rawPostData
        );
    }

    public function removePeer(string $wgDaemonEndpoint, string $publicKey): void
    {
        $rawPostData = implode('&', ['PublicKey='.urlencode($publicKey)]);

        // XXX catch errors
        $httpResponse = $this->httpClient->postRaw(
            $wgDaemonEndpoint.'/remove_peer',
            [],
            $rawPostData
        );
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

    /**
     * @return array{PublicKey:string,ListenPort:int,Peers:array}
     */
    public function getInfo(string $wgDaemonEndpoint): array
    {
        // XXX catch errors
        // XXX make sure WG "backend" is in sync with local DB (somehow)
        $httpResponse = $this->httpClient->get($wgDaemonEndpoint.'/info', [], []);

        return Json::decode($httpResponse->getBody());
    }
}
