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
use LC\Portal\Config;
use LC\Portal\Http\BasicAuthenticationHook;
use LC\Portal\Http\InternalApiModule;
use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\Service;
use LC\Portal\Json;
use LC\Portal\Logger;
use LC\Portal\Storage;
use LC\Portal\TlsAuth;

$logger = new Logger('vpn-server-api');

try {
    // this is provided by Apache, using CanonicalName
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    $configDir = sprintf('%s/config', $baseDir);

    $config = Config::fromFile(
        sprintf('%s/config.php', $configDir)
    );

    $service = new Service();
    $basicAuthentication = new BasicAuthenticationHook(
        $config->getSection('apiConsumers')->toArray(),
        'vpn-server-backend'
    );
    $service->addBeforeHook('auth', $basicAuthentication);

    $storage = new Storage(
        new PDO(
            sprintf('sqlite://%s/db.sqlite', $dataDir)
        ),
        sprintf('%s/schema', $baseDir)
    );
    $storage->update();

    $easyRsaDir = sprintf('%s/easy-rsa', $baseDir);
    $easyRsaDataDir = sprintf('%s/easy-rsa', $dataDir);

    $easyRsaCa = new EasyRsaCa(
        $easyRsaDir,
        $easyRsaDataDir
    );
    $tlsAuth = new TlsAuth($dataDir);

    $service->addModule(
        new InternalApiModule(
            $easyRsaCa,
            $tlsAuth,
            $config,
            $storage
        )
    );

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new Response(500, 'application/json');
    $response->setBody(Json::encode(['error' => $e->getMessage()]));
    $response->send();
}
