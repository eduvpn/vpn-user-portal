<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OpenVpn;

use LC\OpenVpn\ConnectionManager;
use LC\OpenVpn\ManagementSocketInterface;
use Psr\Log\LoggerInterface;

/**
 * Manage all OpenVPN processes controlled by this service.
 */
class ServerManager
{
    /** @var array<string,\LC\Portal\Config\ProfileConfig> */
    private $profileConfigList;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var \LC\OpenVpn\ManagementSocketInterface */
    private $managementSocket;

    /**
     * @param array<string,\LC\Portal\Config\ProfileConfig> $profileConfigList
     * @param \Psr\Log\LoggerInterface                      $logger
     * @param \LC\OpenVpn\ManagementSocketInterface         $managementSocket
     */
    public function __construct(array $profileConfigList, LoggerInterface $logger, ManagementSocketInterface $managementSocket)
    {
        $this->profileConfigList = $profileConfigList;
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
        foreach ($this->profileConfigList as $profileId => $profileConfig) {
            $managementIp = $profileConfig->getManagementIp();
            $profileNumber = $profileConfig->getProfileNumber();

            $profileConnections = [];
            $socketAddressList = [];
            for ($i = 0; $i < \count($profileConfig->getVpnProtoPorts()); ++$i) {
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
        foreach ($this->profileConfigList as $profileId => $profileConfig) {
            $managementIp = $profileConfig->getManagementIp();
            $profileNumber = $profileConfig->getProfileNumber();
            for ($i = 0; $i < \count($profileConfig->getVpnProtoPorts()); ++$i) {
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
