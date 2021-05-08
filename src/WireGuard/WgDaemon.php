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

    public function getPeers(string $wgDaemonEndpoint, string $wgDevice): array
    {
        $wgInfo = $this->getInfo($wgDaemonEndpoint, $wgDevice);

        return \array_key_exists('Peers', $wgInfo) ? $wgInfo['Peers'] : [];
    }

    public function addPeer(string $wgDaemonEndpoint, string $wgDevice, string $publicKey, string $ipFour, string $ipSix): void
    {
        $wgInfo = $this->getInfo($wgDaemonEndpoint, $wgDevice);
        $rawPostData = implode(
            '&',
            [
                'Device='.$wgDevice,
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

    public function removePeer(string $wgDaemonEndpoint, string $wgDevice, string $publicKey): void
    {
        $rawPostData = implode('&', ['Device='.$wgDevice, 'PublicKey='.urlencode($publicKey)]);

        // XXX catch errors
        $httpResponse = $this->httpClient->postRaw(
            $wgDaemonEndpoint.'/remove_peer',
            [],
            $rawPostData
        );
    }

    /**
     * Very inefficient way to register all peers (again) with WG. This is only
     * done for peers that are not using the API as we assume that API users
     * we perform the right API calls to add/remove their config.
     */
    public function syncPeers(string $wgDaemonEndpoint, string $wgDevice, array $peerInfoList): void
    {
        // XXX it does not remove anything, not really good!
        foreach ($peerInfoList as $peerInfo) {
            if (null === $peerInfo['client_id']) {
                // do not add peers that are registered through the API
                $this->addPeer($wgDaemonEndpoint, $wgDevice, $peerInfo['public_key'], $peerInfo['ip_four'], $peerInfo['ip_six']);
            }
        }
    }

    /**
     * @return array{PublicKey:string,ListenPort:int,Peers:array}
     */
    public function getInfo(string $wgDaemonEndpoint, string $wgDevice): array
    {
        // XXX catch errors
        // XXX make sure WG "backend" is in sync with local DB (somehow)
        $httpResponse = $this->httpClient->get($wgDaemonEndpoint.'/info', ['Device' => $wgDevice], []);

        return Json::decode($httpResponse->getBody());
    }
}
