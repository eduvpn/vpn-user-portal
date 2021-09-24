<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateTimeImmutable;
use LC\Portal\HttpClient\HttpClientInterface;

/**
 * List, add and remove connections.
 */
class ConnectionManager
{
    protected DateTimeImmutable $dateTime;
    private Config $config;
    private Storage $storage;
    private HttpClientInterface $httpClient;

    public function __construct(Config $config, HttpClientInterface $httpClient, Storage $storage)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->httpClient = $httpClient;
        $this->dateTime = new DateTimeImmutable();
    }

    /**
     * @return array<string,array<array{user_id:string,connection_id:string,display_name:string,ip_list:array<string>}>>
     */
    public function get(): array
    {
        $connectionList = [];
        // keep the record of all nodeBaseUrls we talked to so we only hit them
        // once... multiple profiles can have the same nodeBaseUrl if the run
        // on the same machine/VM
        $nodeBaseUrlList = [];
        foreach ($this->config->profileConfigList() as $profileConfig) {
            if (\in_array($profileConfig->nodeBaseUrl(), $nodeBaseUrlList, true)) {
                $nodeBaseUrlList[] = $profileConfig->nodeBaseUrl();

                continue;
            }

            $profileId = $profileConfig->profileId();
            $connectionList[$profileId] = [];

            if ('openvpn' === $profileConfig->vpnProto()) {
                // OpenVPN
                $certificateList = $this->storage->oCertListByProfileId($profileId);
                // XXX error handling
                $daemonConnectionList = Json::decode(
                    $this->httpClient->get($profileConfig->nodeBaseUrl().'/o/connection_list')->body()
                );
                $o = [];
                foreach ($daemonConnectionList['connection_list'] as $connectionEntry) {
                    $o[$connectionEntry['common_name']] = $connectionEntry;
                }

                foreach ($certificateList as $cl) {
                    if (\array_key_exists($cl['common_name'], $o)) {
                        // found it!
                        $commonName = $cl['common_name'];
                        $connectionList[$profileId][] = [
                            'user_id' => $cl['user_id'],
                            'connection_id' => $cl['common_name'],
                            'display_name' => $cl['display_name'],
                            'ip_list' => [$o[$commonName]['ip_four'], $o[$commonName]['ip_six']],
                        ];
                    }
                }

                continue;
            }

            // WireGuard
            $storageWgPeerList = $this->storage->wPeerListByProfileId($profileId);
            // XXX error handling
            $daemonWgPeerList = Json::decode(
                $this->httpClient->get($profileConfig->nodeBaseUrl().'/w/peer_list', [])->body()
            );

            $w = [];
            foreach ($daemonWgPeerList['peer_list'] as $peerEntry) {
                $w[$peerEntry['public_key']] = $peerEntry;
            }

            foreach ($storageWgPeerList as $pl) {
                if (\array_key_exists($pl['public_key'], $w)) {
                    // found it!
                    $connectionList[$profileId][] = [
                        'user_id' => $pl['user_id'],
                        'connection_id' => $pl['public_key'],
                        'display_name' => $pl['display_name'],
                        'ip_list' => [$pl['ip_four'], $pl['ip_six']],
                    ];
                }
            }
        }

        return $connectionList;
    }

    /**
     * Remove and disconnect all VPN clients connected with this
     * OAuth authorization ("auth_key"). This works because VPN
     * clients are not allowed to be connected more than once.
     * In the normal situation there is only 1 active connection
     * when "/disconnect" is called, but in case a previous
     * "/disconnect" was never sent, e.g. because the client
     * crashed this also functions as a "cleanup" of sorts.
     */
    public function disconnectByAuthKey(string $authKey): void
    {
        // OpenVPN
        foreach ($this->storage->oCertListByAuthKey($authKey) as $oCertInfo) {
            $this->disconnect(
                $oCertInfo['user_id'],
                $oCertInfo['profile_id'],
                $oCertInfo['common_name']
            );
        }

        // WireGuard
        foreach ($this->storage->wPeerListByAuthKey($authKey) as $wPeerInfo) {
            $this->disconnect(
                $wPeerInfo['user_id'],
                $wPeerInfo['profile_id'],
                $wPeerInfo['public_key']
            );
        }
    }

    public function disconnectByUserId(string $userId): void
    {
        // OpenVPN
        foreach ($this->storage->oCertListByUserId($userId) as $oCertInfo) {
            $this->disconnect(
                $userId,
                $oCertInfo['profile_id'],
                $oCertInfo['common_name']
            );
        }

        // WireGuard
        foreach ($this->storage->wPeerListByUserId($userId) as $wPeerInfo) {
            $this->disconnect(
                $userId,
                $wPeerInfo['profile_id'],
                $wPeerInfo['public_key']
            );
        }
    }

    public function disconnect(string $userId, string $profileId, string $connectionId): void
    {
        // TODO:
        // - record connect/disconnect event for WG
        // - should we also have a profileId as a parameter to limit the
        //   number of processes we have to access? BUT we MUST make sure we
        //   disconnect/removepeer clients that are connected to profiles that
        //   no longer exist... or does apply-changes take care of that? Maybe
        //   not for WG
        //
        // keep the record of all nodeBaseUrls we talked to so we only hit them
        // once... multiple profiles can have the same nodeBaseUrl if the run
        // on the same machine/VM
        //
        // XXX profileId is needed one way or the other to prevent sending
        // OpenVPN disconnects to WG and vice versa
        // XXX do NOT keep this a foreach, this is so sucky haha
        foreach ($this->config->profileConfigList() as $profileConfig) {
            if ($profileId !== $profileConfig->profileId()) {
                continue;
            }
            if ('openvpn' === $profileConfig->vpnProto()) {
                $this->storage->oCertDelete($userId, $connectionId);
                // XXX error handling
                $this->httpClient->post($profileConfig->nodeBaseUrl().'/o/disconnect_client', [], ['common_name' => $connectionId]);

                continue;
            }

            // WireGuard
            $this->storage->wPeerRemove($userId, $connectionId);
            // XXX error handling
            // XXX it doesn't seem to be working! the peer is removed from the DB, but not from the WG process...
            // (using OAuth client)
            $this->httpClient->post($profileConfig->nodeBaseUrl().'/w/remove_peer', [], ['public_key' => $connectionId]);
        }
    }
}
