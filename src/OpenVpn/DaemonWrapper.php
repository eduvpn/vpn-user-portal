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
use LC\Portal\LoggerInterface;
use LC\Portal\ProfileConfig;
use LC\Portal\Storage;
use RangeException;
use RuntimeException;

/**
 * Use vpn-daemon instead of direct OpenVPN management port socket connections.
 */
class DaemonWrapper
{
    private Config $config;

    private Storage $storage;

    private DaemonSocket $daemonSocket;

    private LoggerInterface $logger;

    public function __construct(Config $config, Storage $storage, DaemonSocket $daemonSocket, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->daemonSocket = $daemonSocket;
        $this->logger = $logger;
    }

    public function getConnectionList(?string $userId, ?string $clientId): array
    {
        // XXX be very explicit about the array we get back
        // figure out the nodeIp + portList for each profile...
        $profileNodeIpPortList = [];
        foreach ($this->config->requireArray('vpnProfiles') as $profileId => $profileData) {
            $profileNodeIpPortList[$profileId] = [];
            $profileConfig = new ProfileConfig(new Config($profileData));
            $profileNodeIpPortList[$profileId]['nodeIp'] = $profileConfig->nodeIp();
            $profileNumber = $profileConfig->profileNumber();
            $profileNodeIpPortList[$profileId]['portList'] = [];
            for ($i = 0; $i < \count($profileConfig->vpnProtoPorts()); ++$i) {
                $profileNodeIpPortList[$profileId]['portList'][] = 11940 + self::toPort($profileNumber, $i);
            }
        }

        // walk over every profile, fetch the connected clients for
        // that profile and augment it with info from storage,
        // optionally filter it...
        //
        // FIXME: do not connect (again) as long as nodeIp
        // remains the same!
        $connectionList = [];
        foreach ($profileNodeIpPortList as $profileId => $nodeIpPortList) {
            $connectionList[$profileId] = [];
            $nodeIp = $nodeIpPortList['nodeIp'];
            $portList = $nodeIpPortList['portList'];
            try {
                $this->daemonSocket->open($nodeIp);
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
                $this->logger->warning(sprintf('DAEMON %s [%s]: %s', $nodeIp, implode(',', $portList), $e->getMessage()));
            }
            $this->daemonSocket->close();
        }

        return $connectionList;
    }

    public function killClient(string $commonName): void
    {
        $nodeIpPortList = [];
        foreach ($this->config->requireArray('vpnProfiles') as $profileData) {
            $profileConfig = new ProfileConfig(new Config($profileData));
            $nodeIp = $profileConfig->nodeIp();
            if (!\array_key_exists($nodeIp, $nodeIpPortList)) {
                // multiple profiles can have the same nodeIp
                $nodeIpPortList[$nodeIp] = [];
            }
            $profileNumber = $profileConfig->profileNumber();
            for ($i = 0; $i < \count($profileConfig->vpnProtoPorts()); ++$i) {
                $nodeIpPortList[$nodeIp][] = 11940 + self::toPort($profileNumber, $i);
            }
        }

        // send the "DISCONNECT" command for all IPs and/ports
        foreach ($nodeIpPortList as $nodeIp => $portList) {
            try {
                $this->daemonSocket->open($nodeIp);
                $this->daemonSocket->setPorts($portList);
                $this->daemonSocket->disconnect([$commonName]);
            } catch (RuntimeException $e) {
                // can't do much here...
                $this->logger->warning(sprintf('DAEMON %s [%s]: %s', $nodeIp, implode(',', $portList), $e->getMessage()));
            }
            $this->daemonSocket->close();
        }
    }

    public static function toPort(int $profileNumber, int $processNumber): int
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
