<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OpenVpn;

use LC\Common\Config;
use LC\Common\ProfileConfig;
use LC\OpenVpn\ConnectionManager;
use LC\OpenVpn\ManagementSocketInterface;
use Psr\Log\LoggerInterface;

/**
 * Manage all OpenVPN processes controlled by this service.
 */
class ServerManager
{
    /** @var \LC\Common\Config */
    private $config;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var \LC\OpenVpn\ManagementSocketInterface */
    private $managementSocket;

    public function __construct(Config $config, LoggerInterface $logger, ManagementSocketInterface $managementSocket)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->managementSocket = $managementSocket;
    }

    /**
     * @return array<string,array>
     */
    public function connections()
    {
        $clientConnections = [];

        // loop over all profiles
        foreach (array_keys($this->config->getSection('vpnProfiles')->toArray()) as $profileId) {
            $profileConfig = new ProfileConfig($this->config->getSection('vpnProfiles')->getSection($profileId)->toArray());
            $managementIp = $profileConfig->getItem('managementIp');
            $profileNumber = $profileConfig->getItem('profileNumber');

            $profileConnections = [];
            $socketAddressList = [];
            for ($i = 0; $i < \count($profileConfig->getItem('vpnProtoPorts')); ++$i) {
                $socketAddressList[] = sprintf(
                    'tcp://%s:%d',
                    $managementIp,
                    11940 + $this->toPort($profileNumber, $i)
                );
            }

            $connectionManager = new ConnectionManager($socketAddressList, $this->logger, $this->managementSocket);
            $profileConnections += $connectionManager->connections();
            $clientConnections[$profileId] = $profileConnections;
        }

        return $clientConnections;
    }

    /**
     * @param string $commonName
     *
     * @return int
     */
    public function kill($commonName)
    {
        $socketAddressList = [];

        // loop over all profiles
        foreach (array_keys($this->config->getSection('vpnProfiles')->toArray()) as $profileId) {
            $profileConfig = new ProfileConfig($this->config->getSection('vpnProfiles')->getSection($profileId)->toArray());
            $managementIp = $profileConfig->getItem('managementIp');
            $profileNumber = $profileConfig->getItem('profileNumber');

            for ($i = 0; $i < \count($profileConfig->getItem('vpnProtoPorts')); ++$i) {
                $socketAddressList[] = sprintf(
                    'tcp://%s:%d',
                    $managementIp,
                    11940 + $this->toPort($profileNumber, $i)
                );
            }
        }

        $connectionManager = new ConnectionManager($socketAddressList, $this->logger, $this->managementSocket);

        return $connectionManager->disconnect([$commonName]);
    }

    /**
     * @param int $profileNumber
     * @param int $processNumber
     *
     * @return int
     */
    private function toPort($profileNumber, $processNumber)
    {
        // we have 2^16 - 11940 ports available for management ports, so let's
        // say we have 2^14 ports available to distribute over profiles and
        // processes, let's take 12 bits, so we have 64 profiles with each 64
        // processes...
        return ($profileNumber - 1 << 6) | $processNumber;
    }
}
