<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Ini\IniReader;
use fkooman\VPN\UserPortal\PdoStorage;
use fkooman\VPN\UserPortal\VpnConfigApiClient;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Basic\BasicAuthentication;
use GuzzleHttp\Client;
use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\VPN\UserPortal\Utils;
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

    // VPN Config API Configuration
    $serviceUri = $iniReader->v('VpnConfigApi', 'serviceUri');
    $serviceAuth = $iniReader->v('VpnConfigApi', 'serviceUser');
    $servicePass = $iniReader->v('VpnConfigApi', 'servicePass');

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
        '/revoke',
        function (Request $request) use ($VpnConfigApiClient, $db) {
            $userId = $request->getPostParameter('user_id');
            $configName = $request->getPostParameter('config_name');

            // XXX: validate user_id
            Utils::validateConfigName($configName);
            $VpnConfigApiClient->revokeConfiguration($userId, $configName);
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

    // get user list
    $service->get(
        '/users',
        function (Request $request) use ($db) {
            $userList = array();
            $users = $db->getUsers();
            $blockedUsers = $db->getBlockedUsers();

            foreach ($users as $user) {
                $userList[] = array('user_id' => $user, 'is_blocked' => in_array($user, $blockedUsers));
            }

            $response = new JsonResponse();
            $response->setBody(
                array('items' => $userList)
            );

            return $response;
        }
    );

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
    error_log($e->getMessage());
    die(sprintf('ERROR: %s', $e->getMessage()));
}
