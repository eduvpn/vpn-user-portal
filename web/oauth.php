<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use fkooman\OAuth\Server\Signer;
use Vpn\Portal\Config;
use Vpn\Portal\Dt;
use Vpn\Portal\Expiry;
use Vpn\Portal\FileIO;
use Vpn\Portal\Http\JsonResponse;
use Vpn\Portal\Http\OAuthTokenModule;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Service;
use Vpn\Portal\OAuth\ClientDb;
use Vpn\Portal\OAuth\VpnOAuthServer;
use Vpn\Portal\OpenVpn\CA\VpnCa;
use Vpn\Portal\Storage;
use Vpn\Portal\SysLogger;

// only allow owner permissions
umask(0077);

$logger = new SysLogger('vpn-user-portal');

try {
    $request = Request::createFromGlobals();
    FileIO::mkdir($baseDir.'/data');

    $config = Config::fromFile($baseDir.'/config/config.php');
    $service = new Service();
    $storage = new Storage($config->dbConfig($baseDir));
    $ca = new VpnCa($baseDir.'/config/ca', $config->vpnCaPath());

    $sessionExpiry = Expiry::calculate(
        Dt::get(),
        $ca->caCert()->validTo(),
        $config->sessionExpiry()
    );

    // OAuth module
    $oauthServer = new VpnOAuthServer(
        new OAuthStorage($storage->dbPdo(), 'oauth_'),
        new ClientDb(),
        new Signer(FileIO::read($baseDir.'/config/oauth.key')),
        $sessionExpiry,
        $config->apiConfig()->tokenExpiry()
    );

    $oauthModule = new OAuthTokenModule(
        $oauthServer
    );
    $service->addModule($oauthModule);
    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], [], 500);
    $response->send();
}
