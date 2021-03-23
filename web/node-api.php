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

use LC\Common\Config;
use LC\Common\FileIO;
use LC\Common\Http\BearerAuthenticationHook;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Json;
use LC\Common\Logger;
use LC\Portal\CA\VpnCa;
use LC\Portal\NodeApiModule;
use LC\Portal\Storage;
use LC\Portal\TlsCrypt;

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    $configDir = sprintf('%s/config', $baseDir);
    $config = Config::fromFile(
        sprintf('%s/config.php', $configDir)
    );

    $service = new Service();
    $bearerAuthentication = new BearerAuthenticationHook(
        FileIO::readFile($configDir.'/node.key'),
        'Node API'
    );
    $service->addBeforeHook('auth', $bearerAuthentication);

    $storage = new Storage(
        new PDO(
            sprintf('sqlite://%s/db.sqlite', $dataDir)
        ),
        sprintf('%s/schema', $baseDir)
    );
    $storage->update();
    $vpnCaDir = sprintf('%s/ca', $dataDir);
    $vpnCaPath = $config->requireString('vpnCaPath', '/usr/bin/vpn-ca');
    $ca = new VpnCa($vpnCaDir, 'EdDSA', $vpnCaPath);

    $service->addModule(
        new NodeApiModule(
            $config,
            $ca,
            $storage,
            new TlsCrypt($dataDir)
        )
    );
    $service->run($request)->send();
} catch (Exception $e) {
    $logger = new Logger('vpn-user-portal-api');
    $logger->error($e->getMessage());
    $response = new Response(500, 'application/json');
    $response->setBody(Json::encode(['error' => $e->getMessage()]));
    $response->send();
}
