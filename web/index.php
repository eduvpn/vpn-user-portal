<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Ini\IniReader;
use fkooman\VpnPortal\PdoStorage;
use fkooman\VpnPortal\VpnPortalService;
use fkooman\VpnPortal\VpnCertServiceClient;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Mellon\MellonAuthentication;
use fkooman\Rest\Plugin\Authentication\Basic\BasicAuthentication;
use fkooman\Tpl\Twig\TwigTemplateManager;
use GuzzleHttp\Client;
use fkooman\Http\Request;

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
    $pdoStorage = new PdoStorage($pdo);

    // Authentication
    $authMethod = $iniReader->v('authMethod');
    switch ($authMethod) {
        case 'MellonAuthentication':
            $auth = new MellonAuthentication(
                $iniReader->v('MellonAuthentication', 'attribute')
            );
            break;
        case 'BasicAuthentication':
            $auth = new BasicAuthentication(
                function ($userId) use ($iniReader) {
                    $userList = $iniReader->v('BasicAuthentication');
                    if (!array_key_exists($userId, $userList)) {
                        return false;
                    }

                    return $userList[$userId];
                },
                array('realm' => 'VPN User Portal')
            );
            break;
        default:
            throw new RuntimeException('unsupported authentication mechanism');
    }

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

    $templateManager = new TwigTemplateManager(
        array(
            dirname(__DIR__).'/views',
            dirname(__DIR__).'/config/views',
        ),
        $iniReader->v('templateCache', false, null)
    );
    $templateManager->setDefault(
        array(
            'rootFolder' => $request->getUrl()->getRoot(),
        )
    );

    $vpnCertServiceClient = new VpnCertServiceClient($client, $serviceUri);

    $service = new VpnPortalService(
        $pdoStorage,
        $templateManager,
        $vpnCertServiceClient
    );

    $authenticationPlugin = new AuthenticationPlugin();
    $authenticationPlugin->register($auth, 'user');
    $authenticationPlugin->register($apiAuth, 'api');
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    $service->run($request)->send();
} catch (Exception $e) {
    error_log($e->getMessage());
    die(sprintf('ERROR: %s', $e->getMessage()));
}
