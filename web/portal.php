<?php

require_once dirname(__DIR__)."/vendor/autoload.php";

use fkooman\Http\Exception\HttpException;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\Config\Config;
use fkooman\VpnPortal\PdoStorage;
use fkooman\VpnPortal\VpnPortalService;
use fkooman\VpnPortal\VpnCertServiceClient;
use fkooman\Rest\Plugin\Mellon\MellonAuthentication;
use GuzzleHttp\Client;

set_error_handler(
    function ($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
);

try {
    $config = Config::fromIniFile(
        dirname(__DIR__)."/config/config.ini"
    );

    $pdo = new PDO(
        $config->s('PdoStorage')->l('dsn', true),
        $config->s('PdoStorage')->l('username', false),
        $config->s('PdoStorage')->l('password', false)
    );

    // Database
    $pdoStorage = new PdoStorage($pdo);

    // Authentication
    $mellonAuthentication = new MellonAuthentication(
        $config->l('mellonAttribute', true)
    );

    // VPN Certificate Service Configuration
    $serviceUri = $config->s('VpnCertService', true)->l('serviceUri', true);
    $serviceAuth = $config->s('VpnCertService', true)->l('serviceUser', true);
    $servicePass = $config->s('VpnCertService', true)->l('servicePass', true);

    $client = new Client(
        array(
            'defaults' => array(
                'auth' => array($serviceAuth, $servicePass),
            ),
        )
    );

    $vpnCertServiceClient = new VpnCertServiceClient($client, $serviceUri);

    $vpnPortalService = new VpnPortalService($pdoStorage, $vpnCertServiceClient);
    $vpnPortalService->registerBeforeEachMatchPlugin($mellonAuthentication);
    $vpnPortalService->run()->sendResponse();
} catch (Exception $e) {
    if ($e instanceof HttpException) {
        $response = $e->getHtmlResponse();
    } else {
        // we catch all other (unexpected) exceptions and return a 500
        error_log($e->getTraceAsString());
        $e = new InternalServerErrorException($e->getMessage());
        $response = $e->getHtmlResponse();
    }
    $response->sendResponse();
}
