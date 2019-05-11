<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Portal\CA\EasyRsaCa;
use LC\Portal\Config\PortalConfig;
use LC\Portal\FileIO;
use LC\Portal\Http\BasicAuthenticationHook;
use LC\Portal\Http\NodeApiModule;
use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\Service;
use LC\Portal\Json;
use LC\Portal\Logger;
use LC\Portal\Node\LocalNodeApi;
use LC\Portal\Storage;
use LC\Portal\TlsCrypt;

$logger = new Logger('vpn-user-portal');

try {
    $configDir = sprintf('%s/config', $baseDir);
    $dataDir = sprintf('%s/data', $baseDir);

    // this is provided by Apache, using CanonicalName
    $request = new Request($_SERVER, $_GET, $_POST);
    $service = new Service();
    $basicAuthentication = new BasicAuthenticationHook(
        [
            'vpn-server-node' => FileIO::readFile(sprintf('%s/node-api.key', $configDir)),
        ],
        'Node API'
    );
    $service->addBeforeHook('auth', $basicAuthentication);

    $portalConfig = PortalConfig::fromFile(sprintf('%s/config.php', $configDir));
    $storage = new Storage(new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)), sprintf('%s/schema', $baseDir));
    $storage->update();
    $easyRsaCa = new EasyRsaCa(sprintf('%s/easy-rsa', $baseDir), sprintf('%s/easy-rsa', $dataDir));
    $tlsCrypt = new TlsCrypt($dataDir);
    $localNodeApi = new LocalNodeApi($easyRsaCa, $tlsCrypt, $portalConfig, $storage);

    $service->addModule(
        new NodeApiModule(
            $localNodeApi
        )
    );

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new Response(500, 'application/json');
    $response->setBody(Json::encode(['error' => $e->getMessage()]));
    $response->send();
}
