<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Ini\IniReader;
use fkooman\VpnPortal\PdoStorage;
use fkooman\VpnPortal\VpnPortalService;
use fkooman\VpnPortal\VpnCertServiceClient;
use fkooman\Rest\Plugin\Mellon\MellonAuthentication;
use GuzzleHttp\Client;
use fkooman\Rest\ExceptionHandler;
use fkooman\Rest\PluginRegistry;
use fkooman\Rest\Plugin\ReferrerCheck\ReferrerCheckPlugin;

ExceptionHandler::register();

$iniReader = IniReader::fromFile(
    dirname(__DIR__).'/config/config.ini'
);

$pdo = new PDO(
    $iniReader->v('PdoStorage', 'dsn'),
    $iniReader->v('PdoStorage', 'username', false),
    $iniReader->v('PdoStorage', 'password', false)
);

// Database
$pdoStorage = new PdoStorage($pdo);

// Authentication
$mellonAuthentication = new MellonAuthentication(
    $iniReader->v('Authentication', 'mellonAttribute')
);

// VPN Certificate Service Configuration
$serviceUri = $iniReader->v('VpnCertService', 'serviceUri');
$serviceAuth = $iniReader->v('VpnCertService', 'serviceUser');
$servicePass = $iniReader->v('VpnCertService', 'servicePass');

$client = new Client(
    array(
        'defaults' => array(
            'auth' => array($serviceAuth, $servicePass),
        ),
    )
);

$vpnCertServiceClient = new VpnCertServiceClient($client, $serviceUri);

$pluginRegistry = new PluginRegistry();
$pluginRegistry->registerDefaultPlugin($mellonAuthentication);
$pluginRegistry->registerDefaultPlugin(new ReferrerCheckPlugin());

$service = new VpnPortalService(
    $pdoStorage,
    $vpnCertServiceClient
);
$service->setPluginRegistry($pluginRegistry);
$service->run()->send();
