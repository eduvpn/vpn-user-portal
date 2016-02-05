<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\VPN\UserPortal\PdoStorage;
use fkooman\VPN\UserPortal\VpnConfigApiClient;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Basic\BasicAuthentication;
use GuzzleHttp\Client;
use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\VPN\UserPortal\Utils;
use fkooman\Http\JsonResponse;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\VPN\UserPortal\SimpleError;
use fkooman\Config\Reader;
use fkooman\Config\YamlFile;

SimpleError::register();

try {
    $reader = new Reader(
        new YamlFile(dirname(__DIR__).'/config/config.yaml')
    );

    $pdo = new PDO(
        $reader->v('PdoStorage', 'dsn'),
        $reader->v('PdoStorage', 'username', false),
        $reader->v('PdoStorage', 'password', false)
    );

    // Database
    $db = new PdoStorage($pdo);

    $apiAuth = new BasicAuthentication(
        function ($userId) use ($reader) {
            $userList = $reader->v('ApiAuthentication');
            if (!array_key_exists($userId, $userList)) {
                return false;
            }

            return $userList[$userId];
        },
        array('realm' => 'VPN User Portal API')
    );

    // VPN Config API Configuration
    $serviceUri = $reader->v('VpnConfigApi', 'serviceUri');
    $serviceAuth = $reader->v('VpnConfigApi', 'serviceUser');
    $servicePass = $reader->v('VpnConfigApi', 'servicePass');

    $client = new Client(
        array(
            'defaults' => array(
                'auth' => array($serviceAuth, $servicePass),
            ),
        )
    );

    $request = new Request($_SERVER);

    $VpnConfigApiClient = new VpnConfigApiClient($client, $serviceUri);

    $service = new Service();

    $service->post(
        '/blockUser',
        function (Request $request) use ($db) {
            $userId = $request->getPostParameter('user_id');
            $db->blockUser($userId);

            return new JsonResponse();
        }
    );

    $service->post(
        '/unblockUser',
        function (Request $request) use ($db) {
            $userId = $request->getPostParameter('user_id');
            $db->unblockUser($userId);

            return new JsonResponse();
        }
    );

    $authenticationPlugin = new AuthenticationPlugin();
    $authenticationPlugin->register($apiAuth, 'api');
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    $service->run($request)->send();
} catch (Exception $e) {
    // internal server error
    error_log($e->__toString());
    $e = new InternalServerErrorException($e->getMessage());
    $e->getJsonResponse()->send();
}
