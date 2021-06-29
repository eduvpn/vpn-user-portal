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

    /**
     * @return array<string, array<array{common_name:string,display_name:string,expires_at:\DateTimeImmutable,management_port:int,user_id:string,user_is_disabled:bool, virtual_address:array{0:string,1:string}}>>
     */
    public function getConnectionList(?string $userId): array
    {
        // figure out the nodeIp + portList for each profile...
        $profileNodeIpPortList = [];
        foreach ($this->config->profileConfigList() as $profileConfig) {
            if ('openvpn' !== $profileConfig->vpnType()) {
                continue;
            }
            $portList = [];
            for ($i = 0; $i < \count($profileConfig->vpnProtoPorts()); ++$i) {
                $portList[] = 11940 + self::toPort($profileConfig->profileNumber(), $i);
            }
            $profileNodeIpPortList[$profileConfig->profileId()] = [
                'nodeIp' => $profileConfig->nodeIp(),
                'portList' => $portList,
            ];
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
                        'management_port' => $connectionInfo['management_port'],
                        'common_name' => $connectionInfo['common_name'],
                        'virtual_address' => $connectionInfo['virtual_address'],
                        'user_id' => $certInfo['user_id'],
                        'user_is_disabled' => $certInfo['user_is_disabled'],
                        'display_name' => $certInfo['display_name'],
                        'expires_at' => $certInfo['expires_at'],
                    ];
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
        foreach ($this->config->profileConfigList() as $profileConfig) {
            if ('openvpn' !== $profileConfig->vpnType()) {
                continue;
            }
            $nodeIp = $profileConfig->nodeIp();
            if (!\array_key_exists($nodeIp, $nodeIpPortList)) {
                // multiple profiles can have the same nodeIp
                $nodeIpPortList[$nodeIp] = [];
            }
            for ($i = 0; $i < \count($profileConfig->vpnProtoPorts()); ++$i) {
                $nodeIpPortList[$nodeIp][] = 11940 + self::toPort($profileConfig->profileNumber(), $i);
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
