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

    public function wPeerList(string $nodeBaseUrl, bool $showAll): array
    {
        return Json::decode(
            $this->httpClient->get($nodeBaseUrl.'/w/peer_list', ['show_all' => $showAll ? 'yes' : 'no'])->body()
        );
    }

    public function wPeerAdd(string $nodeBaseUrl, string $publicKey, string $ipFour, string $ipSix): void
    {
        // XXX make sure the public key config is overriden if the public key already exists
        $this->httpClient->post(
            $nodeBaseUrl.'/w/add_peer',
            [],
            [
                'public_key' => $publicKey,
                'ip_net' => [$ipFour.'/32', $ipSix.'/128'],
            ]
        );
    }

    public function wPeerRemove(string $nodeBaseUrl, string $publicKey): void
    {
        $this->httpClient->post($nodeBaseUrl.'/w/remove_peer', [], ['public_key' => $publicKey]);
    }

    public function oConnectionList(string $nodeBaseUrl): array
    {
        return Json::decode(
            $this->httpClient->get($nodeBaseUrl.'/o/connection_list')->body()
        );
    }

    public function oDisconnectClient(string $nodeBaseUrl, string $commonName): void
    {
        $this->httpClient->post($nodeBaseUrl.'/o/disconnect_client', [], ['common_name' => $commonName]);
    }
}
