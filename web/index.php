<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\VPN\UserPortal\PdoStorage;
use fkooman\VPN\UserPortal\VpnPortalService;
use fkooman\VPN\UserPortal\VpnConfigApiClient;
use fkooman\VPN\UserPortal\VpnServerApiClient;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Mellon\MellonAuthentication;
use fkooman\Rest\Plugin\Authentication\Basic\BasicAuthentication;
use fkooman\Tpl\Twig\TwigTemplateManager;
use GuzzleHttp\Client;
use fkooman\Http\Request;
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
    $pdoStorage = new PdoStorage($pdo);

    // Authentication
    $authMethod = $reader->v('authMethod');
    switch ($authMethod) {
        case 'MellonAuthentication':
            $auth = new MellonAuthentication(
                $reader->v('MellonAuthentication', 'attribute')
            );
            break;
        case 'BasicAuthentication':
            $auth = new BasicAuthentication(
                function ($userId) use ($reader) {
                    $userList = $reader->v('BasicAuthentication');
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

    $request = new Request($_SERVER);

    $templateManager = new TwigTemplateManager(
        array(
            dirname(__DIR__).'/views',
            dirname(__DIR__).'/config/views',
        ),
        $reader->v('templateCache', false, null)
    );
    $templateManager->setDefault(
        array(
            'rootFolder' => $request->getUrl()->getRoot(),
        )
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
    $vpnConfigApiClient = new VpnConfigApiClient($client, $serviceUri);

    // VPN Server API Configuration
    $serviceUri = $reader->v('VpnServerApi', 'serviceUri');
    $serviceAuth = $reader->v('VpnServerApi', 'serviceUser');
    $servicePass = $reader->v('VpnServerApi', 'servicePass');
    $client = new Client(
        array(
            'defaults' => array(
                'auth' => array($serviceAuth, $servicePass),
            ),
        )
    );
    $vpnServerApiClient = new VpnServerApiClient($client, $serviceUri);

    $service = new VpnPortalService(
        $pdoStorage,
        $templateManager,
        $vpnConfigApiClient,
        $vpnServerApiClient
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
