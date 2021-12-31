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

use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use fkooman\OAuth\Server\Signer;
use Vpn\Portal\Config;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\FileIO;
use Vpn\Portal\Http\ApiService;
use Vpn\Portal\Http\JsonResponse;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\VpnApiThreeModule;
use Vpn\Portal\HttpClient\CurlHttpClient;
use Vpn\Portal\OAuth\ClientDb;
use Vpn\Portal\OAuth\VpnBearerValidator;
use Vpn\Portal\OpenVpn\CA\VpnCa;
use Vpn\Portal\OpenVpn\TlsCrypt;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;
use Vpn\Portal\SysLogger;
use Vpn\Portal\VpnDaemon;
use Vpn\Portal\WireGuard\ServerConfig as WireGuardServerConfig;

// only allow owner permissions
umask(0077);

$logger = new SysLogger('vpn-user-portal');

try {
    $request = Request::createFromGlobals();
    FileIO::createDir($baseDir.'/data');
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage($config->dbConfig($baseDir));
    $oauthStorage = new OAuthStorage($storage->dbPdo(), 'oauth_');
    $ca = new VpnCa($baseDir.'/config/ca', $config->vpnCaPath());
    $oauthKey = FileIO::readFile($baseDir.'/config/oauth.key');
    $bearerValidator = new VpnBearerValidator(
        new Signer($oauthKey),
        new ClientDb(),
        $oauthStorage
    );
    $service = new ApiService($bearerValidator);
    $wireGuardServerConfig = new WireGuardServerConfig($baseDir, $config->wireGuardConfig()->listenPort());
    $serverInfo = new ServerInfo(
        $baseDir,
        $ca,
        new TlsCrypt($baseDir.'/data'),
        $config->wireGuardConfig()->listenPort(),
        Signer::publicKeyFromSecretKey($oauthKey)
    );

    $service->addModule(
        new VpnApiThreeModule(
            $config,
            $storage,
            $serverInfo,
            new ConnectionManager($config, new VpnDaemon(new CurlHttpClient($baseDir.'/config/vpn-daemon'), $logger), $storage, $logger)
        )
    );

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], [], 500);
    $response->send();
}
