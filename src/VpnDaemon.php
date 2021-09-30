<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\HttpClient\HttpClientInterface;
use LC\Portal\HttpClient\HttpClientRequest;

/**
 * Class interfacing with vpn-daemon and preparing the response data to be
 * easier to use from PHP. Also implements a simple cache for the wPeerList and
 * oConnectionList to prevent the need to query the same node multiple times in
 * case of multi profile setups.
 *
 * XXX should the nodeBaseUrl parameter be needed on all methods or part of
 * the constructor?
 */
class VpnDaemon
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @return array<string,array{public_key:string,ip_net:array{0:string,1:string},last_handshake_time:string,bytes_transferred:int}>
     */
    public function wPeerList(string $nodeBaseUrl, bool $showAll): array
    {
        $wPeerList = Json::decode(
            $this->httpClient->send(
                new HttpClientRequest('GET', $nodeBaseUrl.'/w/peer_list', ['show_all' => $showAll ? 'yes' : 'no'])
            )->body()
        );

        $pList = [];
        foreach ($wPeerList['peer_list'] as $peerInfo) {
            $pList[$peerInfo['public_key']] = $peerInfo;
        }

        return $pList;
    }

    // XXX think about adding multiple peers in one call... maybe use JSON content type instead of form encoded?
    public function wPeerAdd(string $nodeBaseUrl, string $publicKey, string $ipFour, string $ipSix): void
    {
        $this->httpClient->send(
            new HttpClientRequest(
                'POST',
                $nodeBaseUrl.'/w/add_peer',
                [],
                [
                    'public_key' => $publicKey,
                    'ip_net' => [$ipFour.'/32', $ipSix.'/128'],
                ]
            )
        );
    }

    // XXX support array for publicKey is probably better!
    public function wPeerRemove(string $nodeBaseUrl, string $publicKey): void
    {
        $this->httpClient->send(
            new HttpClientRequest(
                'POST',
                $nodeBaseUrl.'/w/remove_peer',
                [],
                [
                    'public_key' => $publicKey,
                ]
            )
        );
    }

    /**
     * @return array<string,array{common_name:string,ip_four:string,ip_six:string}>
     */
    public function oConnectionList(string $nodeBaseUrl): array
    {
        $oConnectionList = Json::decode(
            $this->httpClient->send(
                new HttpClientRequest(
                    'GET',
                    $nodeBaseUrl.'/o/connection_list'
                )
            )->body()
        );

        $cList = [];
        foreach ($oConnectionList['connection_list'] as $clientInfo) {
            $cList[$clientInfo['common_name']] = $clientInfo;
        }

        return $cList;
    }

    // XXX support array for commonName is probably better!
    public function oDisconnectClient(string $nodeBaseUrl, string $commonName): void
    {
        $this->httpClient->send(
            new HttpClientRequest(
                'POST',
                $nodeBaseUrl.'/o/disconnect_client',
                [],
                [
                    'common_name' => $commonName,
                ]
            )
        );
    }
}
