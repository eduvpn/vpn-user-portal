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
use LC\Portal\Storage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RangeException;
use RuntimeException;

/**
 * Use vpn-daemon instead of direct OpenVPN management port socket connections.
 */
class DaemonWrapper
{
    /** @var \LC\Common\Config */
    private $config;

    /** @var \LC\Portal\Storage */
    private $storage;

    /** @var DaemonSocket */
    private $daemonSocket;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(Config $config, Storage $storage, DaemonSocket $daemonSocket, LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->daemonSocket = $daemonSocket;
        if (null === $logger) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    /**
     * @param string|null $userId
     * @param string|null $clientId
     *
     * @return array
     */
    public function getConnectionList($userId, $clientId)
    {
        // figure out the managementIp + portList for each profile...
        $profileManagementIpPortList = [];
        foreach ($this->config->requireArray('vpnProfiles') as $profileId => $profileData) {
            $profileManagementIpPortList[$profileId] = [];
            $profileConfig = new ProfileConfig(new Config($profileData));
            $profileManagementIpPortList[$profileId]['managementIp'] = $profileConfig->managementIp();
            $profileNumber = $profileConfig->profileNumber();
            $profileManagementIpPortList[$profileId]['portList'] = [];
            for ($i = 0; $i < \count($profileConfig->vpnProtoPorts()); ++$i) {
                $profileManagementIpPortList[$profileId]['portList'][] = 11940 + self::toPort($profileNumber, $i);
            }
        }

        // walk over every profile, fetch the connected clients for
        // that profile and augment it with info from storage,
        // optionally filter it...
        //
        // FIXME: do not connect (again) as long as managementIp
        // remains the same!
        $connectionList = [];
        foreach ($profileManagementIpPortList as $profileId => $managementIpPortList) {
            $connectionList[$profileId] = [];
            $managementIp = $managementIpPortList['managementIp'];
            $portList = $managementIpPortList['portList'];
            try {
                $this->daemonSocket->open($managementIp);
                $this->daemonSocket->setPorts($portList);
                $daemonConnectionList = $this->daemonSocket->connections();

                foreach ($daemonConnectionList as $connectionInfo) {
                    $commonName = $connectionInfo['common_name'];
                    if (false === $certInfo = $this->storage->getUserCertificateInfo($commonName)) {
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
                    if (null !== $clientId) {
                        // filter by clientId
                        if ($clientId !== $certInfo['client_id']) {
                            continue;
                        }
                    }

                    // add this connection to the list
                    $connectionList[$profileId][] = array_merge($connectionInfo, $certInfo);
                }
            } catch (RuntimeException $e) {
                // can't do much here...
                $this->logger->warning(sprintf('DAEMON %s [%s]: %s', $managementIp, implode(',', $portList), $e->getMessage()));
            }
            $this->daemonSocket->close();
        }

        return $connectionList;
    }

    /**
     * @param string $commonName
     *
     * @return void
     */
    public function killClient($commonName)
    {
        $managementIpPortList = [];
        foreach ($this->config->requireArray('vpnProfiles') as $profileData) {
            $profileConfig = new ProfileConfig(new Config($profileData));
            $managementIp = $profileConfig->managementIp();
            if (!\array_key_exists($managementIp, $managementIpPortList)) {
                // multiple profiles can have the same managementIp
                $managementIpPortList[$managementIp] = [];
            }
            $profileNumber = $profileConfig->profileNumber();
            for ($i = 0; $i < \count($profileConfig->vpnProtoPorts()); ++$i) {
                $managementIpPortList[$managementIp][] = 11940 + self::toPort($profileNumber, $i);
            }
        }

        // send the "DISCONNECT" command for all IPs and/ports
        foreach ($managementIpPortList as $managementIp => $portList) {
            try {
                $this->daemonSocket->open($managementIp);
                $this->daemonSocket->setPorts($portList);
                $this->daemonSocket->disconnect([$commonName]);
            } catch (RuntimeException $e) {
                // can't do much here...
                $this->logger->warning(sprintf('DAEMON %s [%s]: %s', $managementIp, implode(',', $portList), $e->getMessage()));
            }
            $this->daemonSocket->close();
        }
    }

    /**
     * @param int $profileNumber
     * @param int $processNumber
     *
     * @return int
     */
    public static function toPort($profileNumber, $processNumber)
    {
        if (1 > $profileNumber || 64 < $profileNumber) {
            throw new RangeException('1 <= profileNumber <= 64');
        }

        if (0 > $processNumber || 64 <= $processNumber) {
            throw new RangeException('0 <= processNumber < 64');
        }

        // we have 2^16 - 11940 ports available for management ports, so let's
        // say we have 2^14 ports available to distribute over profiles and
        // processes, let's take 12 bits, so we have 64 profiles with each 64
        // processes...
        return ($profileNumber - 1 << 6) | $processNumber;
    }
}
