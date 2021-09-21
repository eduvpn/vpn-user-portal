<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateInterval;
use DateTimeImmutable;
use LC\Portal\HttpClient\HttpClientInterface;

/**
 * List, add and remove connections.
 */
class ConnectionList
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
     * @return array<string,array{user_id:string,connection_id:string,display_name:string,ip_list:array<string>}>
     */
    public function get(): array
    {
        $connectionList = [];
        foreach ($this->config->profileConfigList() as $profileConfig) {
            $profileId = $profileConfig->profileId();
            $connectionList[$profileId] = [];

            if ('openvpn' === $profileConfig->vpnProto()) {
                // OpenVPN
                $certificateList = $this->storage->certificateList($profileId);
                $daemonConnectionList = Json::decode(
                    $this->httpClient->get($profileConfig->nodeBaseUrl().'/o/connection_list', ['profile_id' => $profileId])->getBody()
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
            $storageWgPeerList = $this->storage->wgGetAllPeers($profileId);
            $daemonWgPeerList = Json::decode(
                $this->httpClient->get($profileConfig->nodeBaseUrl().'/w/peer_list', [])->getBody()
            );

            $w = [];
            foreach ($daemonWgPeerList['peer_list'] as $peerEntry) {
                $w[$peerEntry['public_key']] = $peerEntry;
            }

            foreach ($storageWgPeerList as $pl) {
                if (\array_key_exists($pl['public_key'], $w)) {
                    // found it!
                    $publicKey = $pl['public_key'];

                    // XXX make sure IP matches
                    // XXX maybe move this to vpn-daemon itself?!
                    if (null === $w[$publicKey]['last_handshake_time']) {
                        // never seen
                        continue;
                    }

                    // filter out entries that haven't been seen in the last
                    // three minutes
                    $lht = new DateTimeImmutable($w[$publicKey]['last_handshake_time']);
                    $threeMinutesAgo = $this->dateTime->sub(new DateInterval('PT3M'));
                    if ($lht < $threeMinutesAgo) {
                        continue;
                    }

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
}
