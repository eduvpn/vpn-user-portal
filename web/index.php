<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Ini\IniReader;
use fkooman\VPN\UserPortal\PdoStorage;
use fkooman\VPN\UserPortal\VpnPortalService;
use fkooman\VPN\UserPortal\VpnConfigApiClient;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Mellon\MellonAuthentication;
use fkooman\Rest\Plugin\Authentication\Basic\BasicAuthentication;
use fkooman\Tpl\Twig\TwigTemplateManager;
use GuzzleHttp\Client;
use fkooman\Http\Request;
use fkooman\Http\Exception\InternalServerErrorException;

set_error_handler(array('fkooman\Rest\Service', 'handleErrors'));

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

    $VpnConfigApiClient = new VpnConfigApiClient($client, $serviceUri);

    $service = new VpnPortalService(
        $pdoStorage,
        $templateManager,
        $VpnConfigApiClient
    );

    $authenticationPlugin = new AuthenticationPlugin();
    $authenticationPlugin->register($auth, 'user');
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    $service->run($request)->send();
} catch (Exception $e) {
    // internal server error
    error_log($e->__toString());
    $e = new InternalServerErrorException($e->getMessage());
    $e->getHtmlResponse()->send();
}
