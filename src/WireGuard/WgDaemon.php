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
 * Connect to the wg-daemon.
 */
class WgDaemon
{
    const WG_DAEMON_BASE_URL = 'http://localhost:8080';

    /** @var \LC\Portal\HttpClient\HttpClientInterface */
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getPeers(string $wgDevice): array
    {
        $wgInfo = $this->getInfo($wgDevice);

        return \array_key_exists('Peers', $wgInfo) ? $wgInfo['Peers'] : [];
    }

    public function addPeer(string $wgDevice, string $publicKey, string $ipFour, string $ipSix): void
    {
        $wgInfo = $this->getInfo($wgDevice);
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
            self::WG_DAEMON_BASE_URL.'/add_peer',
            [],
            $rawPostData
        );
    }

    public function removePeer(string $wgDevice, string $publicKey): void
    {
        $rawPostData = implode('&', ['Device='.$wgDevice, 'PublicKey='.urlencode($publicKey)]);

        // XXX catch errors
        $httpResponse = $this->httpClient->postRaw(
            self::WG_DAEMON_BASE_URL.'/remove_peer',
            [],
            $rawPostData
        );
    }

    /**
     * Very inefficient way to register all peers (again) with WG. This is only
     * done for peers that are not using the API as we assume that API users
     * we perform the right API calls to add/remove their config.
     */
    public function syncPeers(string $wgDevice, array $peerInfoList): void
    {
        // XXX it does not remove anything, not really good!
        foreach ($peerInfoList as $peerInfo) {
            if (null === $peerInfo['client_id']) {
                // do not add peers that are registered through the API
                $this->addPeer($wgDevice, $peerInfo['public_key'], $peerInfo['ip_four'], $peerInfo['ip_six']);
            }
        }
    }

    /**
     * @return array{PublicKey:string,ListenPort:int,Peers:array}
     */
    private function getInfo(string $wgDevice): array
    {
        // XXX catch errors
        // XXX make sure WG "backend" is in sync with local DB (somehow)
        $httpResponse = $this->httpClient->get(self::WG_DAEMON_BASE_URL.'/info', ['Device' => $wgDevice], []);

        return Json::decode($httpResponse->getBody());
    }
}
