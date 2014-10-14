<?php

require_once dirname(__DIR__)."/vendor/autoload.php";

use fkooman\Http\Request;
use fkooman\Http\IncomingRequest;
use fkooman\Http\Exception\HttpException;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\Config\Config;
use fkooman\VpnPortal\PdoStorage;
use fkooman\VpnPortal\VpnPortalService;
use fkooman\VpnPortal\VpnCertServiceClient;
use fkooman\Rest\Plugin\BasicAuthentication;
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
    $pdoStorage = new PdoStorage($pdo);
    $basicAuthentication = new BasicAuthentication(
        'foo',
        '$2y$10$zx/fEKn2yleZVULfL8bAt.vUg7OSOkzj1VB1PT2jRAqJ4qrDQOypS'
    );

    $serviceUri = $config->s('vpnCertService', true)->l('serviceUri', true);
    $serviceAuth = $config->s('vpnCertService', true)->l('serviceUser', true);
    $servicePass = $config->s('vpnCertService', true)->l('servicePass', true);

    $client = new Client(
        array(
            'defaults' => array(
                'auth' => array($serviceAuth, $servicePass),
            ),
        )
    );

    $vpnCertServiceClient = new VpnCertServiceClient($client, $serviceUri);
    $request = Request::fromIncomingRequest(new IncomingRequest());
    $vpnPortalService = new VpnPortalService($pdoStorage, $basicAuthentication, $vpnCertServiceClient);
    $vpnPortalService->run($request)->sendResponse();
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
