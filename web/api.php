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

use fkooman\OAuth\Server\BearerValidator;
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
use Vpn\Portal\OpenVpn\CA\VpnCa;
use Vpn\Portal\OpenVpn\TlsCrypt;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;
use Vpn\Portal\SysLogger;
use Vpn\Portal\VpnDaemon;
use Vpn\Portal\WireGuard\ServerConfig as WireGuardServerConfig;

$logger = new SysLogger('vpn-user-portal');

try {
    $request = Request::createFromGlobals();
    FileIO::createDir($baseDir.'/data', 0700);
    $config = Config::fromFile($baseDir.'/config/config.php');
    $db = new PDO(
        $config->dbConfig($baseDir)->dbDsn(),
        $config->dbConfig($baseDir)->dbUser(),
        $config->dbConfig($baseDir)->dbPass()
    );

    $storage = new Storage($db, $baseDir.'/schema');
    $storage->update();

    $oauthStorage = new OAuthStorage($db, 'oauth_');
    $ca = new VpnCa($baseDir.'/data/ca', $config->vpnCaPath());

    $oauthKey = FileIO::readFile($baseDir.'/config/oauth.key');
    $oauthSigner = new Signer($oauthKey);
    $bearerValidator = new BearerValidator(
        $oauthStorage,
        new ClientDb(),
        $oauthSigner
    );
    $service = new ApiService($bearerValidator);

    $wireGuardServerConfig = new WireGuardServerConfig(FileIO::readFile($baseDir.'/config/wireguard.secret.key'), $config->wgPort());
    $serverInfo = new ServerInfo(
        $ca,
        new TlsCrypt($baseDir.'/data'),
        FileIO::readFile($baseDir.'/config/wireguard.public.key'),
        $config->wgPort(),
        Signer::publicKeyFromSecretKey($oauthKey)
    );

    $service->addModule(
        new VpnApiThreeModule(
            $config,
            $storage,
            $serverInfo,
            new ConnectionManager($config, new VpnDaemon(new CurlHttpClient($baseDir.'/config/vpn-daemon'), $logger), $storage)
        )
    );

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], [], 500);
    $response->send();
}
