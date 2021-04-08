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

use LC\Portal\CA\VpnCa;
use LC\Portal\Config;
use LC\Portal\FileIO;
use LC\Portal\Http\Auth\NodeAuthModule;
use LC\Portal\Http\NodeApiModule;
use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\Service;
use LC\Portal\Json;
use LC\Portal\ServerConfig;
use LC\Portal\Storage;
use LC\Portal\SysLogger;
use LC\Portal\TlsCrypt;

try {
    $dataDir = sprintf('%s/data', $baseDir);
    $config = Config::fromFile($baseDir.'/config/config.php');

    $service = new Service();
    $service->setAuthModule(
        new NodeAuthModule(
            FileIO::readFile($baseDir.'/config/node.key'),
            'Node API'
        )
    );

    $storage = new Storage(new PDO('sqlite://'.$dataDir.'/db.sqlite'), $baseDir.'/schema');
    $storage->update();
    $vpnCaDir = sprintf('%s/ca', $dataDir);
    $vpnCaPath = $config->requireString('vpnCaPath', '/usr/bin/vpn-ca');
    $ca = new VpnCa($vpnCaDir, 'EdDSA', $vpnCaPath);

    $service->addModule(
        new NodeApiModule(
            $config,
            $storage,
            new ServerConfig($config, $ca, new TlsCrypt($dataDir))
        )
    );
    $request = new Request($_SERVER, $_GET, $_POST);
    $service->run($request)->send();
} catch (Exception $e) {
    $logger = new SysLogger('vpn-user-portal-node-api');
    $logger->error($e->getMessage());
    $response = new Response(500, 'application/json');
    $response->setBody(Json::encode(['error' => $e->getMessage()]));
    $response->send();
}
