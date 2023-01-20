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

use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use fkooman\OAuth\Server\Signer;
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\Dt;
use Vpn\Portal\Expiry;
use Vpn\Portal\FileIO;
use Vpn\Portal\Http\JsonResponse;
use Vpn\Portal\Http\OAuthTokenModule;
use Vpn\Portal\Http\OAuthTokenService;
use Vpn\Portal\Http\Request;
use Vpn\Portal\OAuth\VpnClientDb;
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

    // workaround for macOS/iOS client on server upgrade trying to use the old
    // 2.x OAuth token endpoint on 3.x server
    // @see https://github.com/eduvpn/apple/issues/487
    if ('/token' === $request->getPathInfo()) {
        // with 2.x PATH_INFO is "/token", for 3.x it is "/oauth/token"
        $httpResponse = new JsonResponse(
            [
                'error' => 'invalid_grant',
            ],
            [
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
            ],
            400
        );
        $httpResponse->send();

        exit(0);
    }

    $config = Config::fromFile($baseDir.'/config/config.php');
    $service = new OAuthTokenService();
    $storage = new Storage($config->dbConfig($baseDir));
    $ca = new VpnCa($baseDir.'/config/keys/ca', $config->vpnCaPath());

    $sessionExpiry = Expiry::calculate(
        Dt::get(),
        $ca->caCert()->validTo(),
        $config->sessionExpiry()
    );

    // OAuth module
    $oauthServer = new VpnOAuthServer(
        new OAuthStorage($storage->dbPdo(), 'oauth_'),
        new VpnClientDb(),
        new Signer(FileIO::read($baseDir.'/config/keys/oauth.key')),
        $sessionExpiry,
        $config->apiConfig()->tokenExpiry()
    );
    $oauthServer->setIssuerIdentity($request->getOrigin().'/');

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
