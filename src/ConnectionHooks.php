<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\Cfg\Config;
use Vpn\Portal\Exception\ConnectionHookException;

class ConnectionHooks implements ConnectionHookInterface
{
    private LoggerInterface $logger;

    /** @var array<ConnectionHookInterface> */
    private array $connectionHookList = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function add(ConnectionHookInterface $connectionHook): void
    {
        $this->connectionHookList[] = $connectionHook;
    }

    public function connect(string $userId, string $profileId, string $vpnProto, string $connectionId, string $ipFour, string $ipSix, ?string $originatingIp): void
    {
        foreach ($this->connectionHookList as $connectionHook) {
            try {
                $connectionHook->connect($userId, $profileId, $vpnProto, $connectionId, $ipFour, $ipSix, $originatingIp);
            } catch (ConnectionHookException $e) {
                $this->logger->warning(sprintf('[%s,%s] %s', __METHOD__, get_class($connectionHook), $e->getMessage()));

                throw $e;
            }
        }
    }

    public function disconnect(string $userId, string $profileId, string $vpnProto, string $connectionId, string $ipFour, string $ipSix, int $bytesIn, int $bytesOut): void
    {
        foreach ($this->connectionHookList as $connectionHook) {
            try {
                $connectionHook->disconnect(
                    $userId,
                    $profileId,
                    $vpnProto,
                    $connectionId,
                    $ipFour,
                    $ipSix,
                    $bytesIn,
                    $bytesOut
                );
            } catch (ConnectionHookException $e) {
                // we can't do anything, so log it, but let it go...
                $this->logger->warning(sprintf('[%s,%s] %s', __METHOD__, get_class($connectionHook), $e->getMessage()));
            }
        }
    }

    public static function init(Config $config, Storage $storage, LoggerInterface $logger): self
    {
        $connectionHooks = new self($logger);
        $connectionHooks->add(new ConnectionLogHook($storage));
        if ($config->logConfig()->syslogConnectionEvents()) {
            $connectionHooks->add(new LogConnectionHook($storage, $logger, $config->logConfig()));
        }
        if (null !== $connectScriptPath = $config->connectScriptPath()) {
            $connectionHooks->add(new ScriptConnectionHook($connectScriptPath));
        }

        return $connectionHooks;
    }
}
