<?php

require_once dirname(__DIR__)."/vendor/autoload.php";

use fkooman\Http\Request;
use fkooman\Http\IncomingRequest;
use fkooman\Http\Exception\HttpException;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\Config\Config;
use fkooman\VpnPortal\PdoStorage;
use fkooman\VpnPortal\VpnPortalService;

#set_error_handler(
#    function ($errno, $errstr, $errfile, $errline) {
#        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
#    }
#);

try {
    $config = Config::fromIniFile(
        dirname(__DIR__)."/config/config.ini"
    );

    $db = new PDO(
        $config->s('PdoStorage')->l('dsn', true),
        $config->s('PdoStorage')->l('username', false),
        $config->s('PdoStorage')->l('password', false)
    );
    $db = new PdoStorage($db);

    $vpnPortalService = new VpnPortalService($db);

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
