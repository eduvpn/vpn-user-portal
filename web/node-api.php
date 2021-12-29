<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use Vpn\Portal\Config;
use Vpn\Portal\Http\Auth\NodeAuthModule;
use Vpn\Portal\Http\JsonResponse;
use Vpn\Portal\Http\NodeApiModule;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Service;
use Vpn\Portal\OpenVpn\CA\VpnCa;
use Vpn\Portal\OpenVpn\ServerConfig as OpenVpnServerConfig;
use Vpn\Portal\OpenVpn\TlsCrypt;
use Vpn\Portal\ServerConfig;
use Vpn\Portal\Storage;
use Vpn\Portal\SysLogger;
use Vpn\Portal\WireGuard\ServerConfig as WireGuardServerConfig;

// only allow owner permissions
umask(0077);

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $service = new Service();
    $service->setAuthModule(
        new NodeAuthModule(
            $baseDir,
            'Node API'
        )
    );

    $storage = new Storage($config->dbConfig($baseDir));
    $ca = new VpnCa($baseDir.'/config/ca', $config->vpnCaPath());

    $service->addModule(
        new NodeApiModule(
            $config,
            $storage,
            new ServerConfig(
                new OpenVpnServerConfig($ca, new TlsCrypt($baseDir.'/data')),
                new WireGuardServerConfig($baseDir, $config->wireGuardConfig()->listenPort()),
            ),
            new SysLogger('vpn-user-portal-node-api')
        )
    );
    $request = Request::createFromGlobals();
    $service->run($request)->send();
} catch (Exception $e) {
    $logger = new SysLogger('vpn-user-portal-node-api');
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], [], 500);
    $response->send();
}
