<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\LocalAccessTokenVerifier;
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use fkooman\OAuth\Server\Signer;
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ConnectionHooks;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\FileIO;
use Vpn\Portal\Http\ApiService;
use Vpn\Portal\Http\GuestApiService;
use Vpn\Portal\Http\JsonResponse;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\VpnApiThreeModule;
use Vpn\Portal\HttpClient\CurlHttpClient;
use Vpn\Portal\OAuth\ClientDb;
use Vpn\Portal\OAuth\NullAccessTokenVerifier;
use Vpn\Portal\OpenVpn\CA\VpnCa;
use Vpn\Portal\OpenVpn\TlsCrypt;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\ServerList;
use Vpn\Portal\Storage;
use Vpn\Portal\SysLogger;
use Vpn\Portal\VpnDaemon;

// only allow owner permissions
umask(0077);

$logger = new SysLogger('vpn-user-portal');

try {
    $request = Request::createFromGlobals();
    FileIO::mkdir($baseDir.'/data');
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage($config->dbConfig($baseDir));
    $oauthStorage = new OAuthStorage($storage->dbPdo(), 'oauth_');
    $ca = new VpnCa($baseDir.'/config/keys/ca', $config->vpnCaPath());
    $oauthKey = FileIO::read($baseDir.'/config/keys/oauth.key');

    [,,$localKeyId] = explode('.', $oauthKey, 4);

    if ($config->apiConfig()->enableGuestAccess()) {
        $serverList = new ServerList($baseDir.'/data', $config->apiConfig());
        $bearerValidator = new BearerValidator(
            new Signer($oauthKey, $serverList),
            new NullAccessTokenVerifier()
        );
        $service = new GuestApiService($bearerValidator, $serverList, $storage, $oauthStorage, $localKeyId);
    } else {
        $bearerValidator = new BearerValidator(
            new Signer($oauthKey),
            new LocalAccessTokenVerifier(
                new ClientDb(),
                $oauthStorage
            )
        );
        $service = new ApiService($bearerValidator);
    }

    $serverInfo = new ServerInfo(
        $request->getRootUri(),
        $baseDir.'/data/keys',
        $ca,
        new TlsCrypt($baseDir.'/data/keys'),
        $config->wireGuardConfig()->listenPort(),
        Signer::publicKeyFromSecretKey($oauthKey)
    );

    $connectionManager = new ConnectionManager($config, new VpnDaemon(new CurlHttpClient($baseDir.'/config/keys/vpn-daemon'), $logger), $storage, ConnectionHooks::init($config, $storage, $logger), $logger);

    $service->addModule(
        new VpnApiThreeModule(
            $config,
            $storage,
            $oauthStorage,
            $serverInfo,
            $connectionManager
        )
    );

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], [], 500);
    $response->send();
}
