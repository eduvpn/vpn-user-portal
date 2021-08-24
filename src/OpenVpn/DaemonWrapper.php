<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OpenVpn;

use LC\Portal\Config;
use LC\Portal\HttpClient\HttpClientInterface;
use LC\Portal\Json;
use LC\Portal\LoggerInterface;
use LC\Portal\Storage;

/**
 * Use vpn-daemon instead of direct OpenVPN management port socket connections.
 */
class DaemonWrapper
{
    private Config $config;

    private Storage $storage;

    private HttpClientInterface $httpClient;

    private LoggerInterface $logger;

    public function __construct(Config $config, Storage $storage, HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * @return array<string, array<array{common_name:string,display_name:string,expires_at:\DateTimeImmutable,profile_number:int,process_number:int,user_id:string,user_is_disabled:bool,virtual_address:array{0:string,1:string}}>>
     */
    public function getConnectionList(?string $userId): array
    {
        $connectionList = [];
        foreach ($this->config->profileConfigList() as $profileConfig) {
            if ('openvpn' !== $profileConfig->vpnProto()) {
                continue;
            }
            $profileId = $profileConfig->profileId();
            $connectionList[$profileId] = [];
            $httpResponse = $this->httpClient->get(
                $profileConfig->nodeBaseUrl().'/ovpn/connection_list',
                [
                    'ProfileNumber' => (string) $profileConfig->profileNumber(),
                    'ProcessCount' => (string) \count($profileConfig->vpnProtoPorts()),
                ]
            );

            $connectionList = Json::decode($httpResponse->getBody())['connection_list'];
            foreach ($connectionList as $connectionInfo) {
                $commonName = $connectionInfo['common_name'];
                if (null === $certInfo = $this->storage->getUserCertificateInfo($commonName)) {
                    // we do not have information on this CN, what is going on?!
                    $this->logger->warning(sprintf('"common_name "%s" not found', $commonName));

                    continue;
                }
                if (null !== $userId) {
                    // filter by userId
                    if ($userId !== $certInfo['user_id']) {
                        continue;
                    }
                }

                $connectionList[$profileId][] = [
                    'common_name' => $connectionInfo['common_name'],
                    'virtual_address' => [$connectionInfo['ip_four'], $connectionInfo['ip_six']],   // XXX ip_four and ip_six
                    'profile_number' => (int) $connectionInfo['profile_number'],
                    'process_number' => (int) $connectionInfo['process_number'],
                    'user_id' => $certInfo['user_id'],
                    'user_is_disabled' => $certInfo['user_is_disabled'],    // XXX why?
                    'display_name' => $certInfo['display_name'],            // XXX why?
                    'expires_at' => $certInfo['expires_at'],
                ];
            }
        }

        return $connectionList;
    }

    public function killClient(string $commonName): void
    {
        foreach ($this->config->profileConfigList() as $profileConfig) {
            if ('openvpn' !== $profileConfig->vpnProto()) {
                continue;
            }
            $profileId = $profileConfig->profileId();
            $httpResponse = $this->httpClient->post(
                $profileConfig->nodeBaseUrl().'/ovpn/disconnect',
                [],
                [
                    'CommonName' => $commonName,
                    'ProfileNumber' => (string) $profileConfig->profileNumber(),
                    'ProcessCount' => (string) \count($profileConfig->vpnProtoPorts()),
                ]
            );
        }
    }
}
