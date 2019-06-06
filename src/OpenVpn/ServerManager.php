<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OpenVpn;

use LC\OpenVpn\ConnectionManager;
use LC\OpenVpn\ManagementSocketInterface;
use LC\Portal\Config\PortalConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Manage all OpenVPN processes controlled by this service.
 */
class ServerManager implements ServerManagerInterface
{
    /** @var \LC\Portal\Config\PortalConfig */
    private $portalConfig;

    /** @var \LC\OpenVpn\ManagementSocketInterface */
    private $managementSocket;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /**
     * @param \LC\Portal\Config\PortalConfig        $portalConfig
     * @param \LC\OpenVpn\ManagementSocketInterface $managementSocket
     */
    public function __construct(PortalConfig $portalConfig, ManagementSocketInterface $managementSocket)
    {
        $this->portalConfig = $portalConfig;
        $this->managementSocket = $managementSocket;
        $this->logger = new NullLogger();
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return array<string,array>
     */
    public function connections()
    {
        $clientConnections = [];

        // loop over all profiles
        foreach ($this->portalConfig->getProfileConfigList() as $profileId => $profileConfig) {
            $managementIp = $profileConfig->getManagementIp();
            $profileNumber = $profileConfig->getProfileNumber();

            $profileConnections = [];
            $socketAddressList = [];
            for ($i = 0; $i < \count($profileConfig->getVpnProtoPortList()); ++$i) {
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
        foreach ($this->portalConfig->getProfileConfigList() as $profileConfig) {
            $managementIp = $profileConfig->getManagementIp();
            $profileNumber = $profileConfig->getProfileNumber();
            for ($i = 0; $i < \count($profileConfig->getVpnProtoPortList()); ++$i) {
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
