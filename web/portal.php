<?php

require_once dirname(__DIR__)."/vendor/autoload.php";

use fkooman\Http\Request;
use fkooman\Http\IncomingRequest;
use fkooman\Http\Exception\HttpException;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\Config\Config;
use fkooman\VpnPortal\PdoStorage;
use fkooman\VpnPortal\VpnPortalService;
use fkooman\Rest\Plugin\BasicAuthentication;

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

    $vpnPortalService = new VpnPortalService($pdoStorage, $basicAuthentication);

    $request = Request::fromIncomingRequest(new IncomingRequest());
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
