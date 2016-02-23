<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Config\Reader;
use fkooman\Config\YamlFile;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\Http\Request;
use fkooman\Http\Session;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Basic\BasicAuthentication;
use fkooman\Rest\Plugin\Authentication\Form\FormAuthentication;
use fkooman\Rest\Plugin\Authentication\Mellon\MellonAuthentication;
use fkooman\Rest\Service;
use fkooman\Tpl\Twig\TwigTemplateManager;
use fkooman\VPN\UserPortal\ApiDb;
use fkooman\VPN\UserPortal\VpnApiModule;
use fkooman\VPN\UserPortal\VpnConfigApiClient;
use fkooman\VPN\UserPortal\VpnPortalModule;
use fkooman\VPN\UserPortal\VpnServerApiClient;
use GuzzleHttp\Client;

try {
    $config = new Reader(
        new YamlFile(dirname(__DIR__).'/config/config.yaml')
    );

    $serverMode = $config->v('serverMode', false, 'production');

    $request = new Request($_SERVER);

    $templateManager = new TwigTemplateManager(
        array(
            dirname(__DIR__).'/views',
            dirname(__DIR__).'/config/views',
        ),
        $config->v('templateCache', false, null)
    );
    $templateManager->setDefault(
        array(
            'rootFolder' => $request->getUrl()->getRoot(),
            'rootUrl' => $request->getUrl()->getRootUrl(),
        )
    );

    // Authentication
    $authMethod = $config->v('authMethod');
    $templateManager->addDefault(array('authMethod' => $authMethod));

    switch ($authMethod) {
        case 'MellonAuthentication':
            $auth = new MellonAuthentication(
                $config->v('MellonAuthentication', 'attribute')
            );
            break;
        case 'FormAuthentication':
            $session = new Session(
                'vpn-user-portal',
                array(
                    'secure' => 'development' !== $serverMode,
                )
            );
            $auth = new FormAuthentication(
                function ($userId) use ($config) {
                    $userList = $config->v('FormAuthentication');
                    if (null === $userList || !array_key_exists($userId, $userList)) {
                        return false;
                    }

                    return $userList[$userId];
                },
                $templateManager,
                $session
            );
            break;

        default:
            throw new RuntimeException('unsupported authentication mechanism');
    }

    // VPN Config API Configuration
    $serviceUri = $config->v('VpnConfigApi', 'serviceUri');
    $serviceAuth = $config->v('VpnConfigApi', 'serviceUser');
    $servicePass = $config->v('VpnConfigApi', 'servicePass');
    $client = new Client(
        array(
            'defaults' => array(
                'auth' => array($serviceAuth, $servicePass),
            ),
        )
    );
    $vpnConfigApiClient = new VpnConfigApiClient($client, $serviceUri);

    // VPN Server API Configuration
    $serviceUri = $config->v('VpnServerApi', 'serviceUri');
    $serviceAuth = $config->v('VpnServerApi', 'serviceUser');
    $servicePass = $config->v('VpnServerApi', 'servicePass');
    $client = new Client(
        array(
            'defaults' => array(
                'auth' => array($serviceAuth, $servicePass),
            ),
        )
    );
    $vpnServerApiClient = new VpnServerApiClient($client, $serviceUri);

    $db = new PDO(
        $config->v('ApiDb', 'dsn', false, sprintf('sqlite://%s/data/api.sqlite', dirname(__DIR__))),
        $config->v('ApiDb', 'username', false),
        $config->v('ApiDb', 'password', false)
    );
    $apiDb = new ApiDb($db);

    $vpnPortalModule = new VpnPortalModule(
        $templateManager,
        $vpnConfigApiClient,
        $vpnServerApiClient,
        $apiDb
    );
    $vpnPortalModule->setCompanionAppUrl(
        $config->v('companionAppUrl', false)
    );

    $vpnApiModule = new VpnApiModule(
        $templateManager,
        $apiDb,
        $vpnConfigApiClient
    );

    $apiAuth = new BasicAuthentication(
        function ($userName) use ($apiDb) {
            if (false === $userInfo = $apiDb->getHashForUserName($userName)) {
                return false;
            }

            return $userInfo['user_pass_hash'];
        },
        array('realm' => 'VPN User API')
    );

    $service = new Service();
    $service->addModule($vpnPortalModule);
    $service->addModule($vpnApiModule);
    $authenticationPlugin = new AuthenticationPlugin();
    $authenticationPlugin->register($auth, 'user');
    $authenticationPlugin->register($apiAuth, 'api');
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    $response = $service->run($request);
    # CSP: https://developer.mozilla.org/en-US/docs/Security/CSP
    # img-src 'self' data: is apparently unsafe! XXX
    $response->setHeader('Content-Security-Policy', "default-src 'self'; img-src 'self' data:");
    # X-Frame-Options: https://developer.mozilla.org/en-US/docs/HTTP/X-Frame-Options
    $response->setHeader('X-Frame-Options', 'DENY');
    $response->send();
} catch (Exception $e) {
    // internal server error
    error_log($e->__toString());
    $e = new InternalServerErrorException($e->getMessage());
    $e->getHtmlResponse()->send();
}
