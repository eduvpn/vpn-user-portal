<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Ini\IniReader;
use fkooman\VpnPortal\PdoStorage;
use fkooman\VpnPortal\VpnCertServiceClient;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Basic\BasicAuthentication;
use GuzzleHttp\Client;
use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\VpnPortal\Utils;
use fkooman\Http\JsonResponse;

try {
    $iniReader = IniReader::fromFile(
        dirname(__DIR__).'/config/config.ini'
    );

    $pdo = new PDO(
        $iniReader->v('PdoStorage', 'dsn'),
        $iniReader->v('PdoStorage', 'username', false),
        $iniReader->v('PdoStorage', 'password', false)
    );

    // Database
    $db = new PdoStorage($pdo);

    $apiAuth = new BasicAuthentication(
        function ($userId) use ($iniReader) {
            $userList = $iniReader->v('ApiAuthentication');
            if (!array_key_exists($userId, $userList)) {
                return false;
            }

            return $userList[$userId];
        },
        array('realm' => 'VPN User Portal API')
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

    $request = new Request($_SERVER);

    $vpnCertServiceClient = new VpnCertServiceClient($client, $serviceUri);

    $service = new Service();

    $service->post(
        '/revoke',
        function (Request $request) use ($vpnCertServiceClient, $db) {
            $userId = $request->getPostParameter('user_id');
            $configName = $request->getPostParameter('config_name');

            // XXX: validate user_id
            Utils::validateConfigName($configName);
            $vpnCertServiceClient->revokeConfiguration($userId, $configName);
            $db->revokeConfiguration($userId, $configName);

            return new JsonResponse();
        }
    );

    $service->get(
        '/configurations',
        function (Request $request) use ($db) {
            $allConfigurations = $db->getAllConfigurations();
            $response = new JsonResponse();
            $response->setBody($allConfigurations);

            return $response;
        }
    );

    $authenticationPlugin = new AuthenticationPlugin();
    $authenticationPlugin->register($apiAuth, 'api');
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    $service->run($request)->send();
} catch (Exception $e) {
    error_log($e->getMessage());
    die(sprintf('ERROR: %s', $e->getMessage()));
}
