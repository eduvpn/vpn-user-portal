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
use fkooman\OAuth\Auth\UnauthenticatedClientAuthentication;
use fkooman\OAuth\Client\CurlHttpClient;
use fkooman\OAuth\Client\OAuth2Client;
use fkooman\OAuth\Client\Provider;
use fkooman\OAuth\OAuthModule;
use fkooman\OAuth\Storage\ArrayClientStorage;
use fkooman\OAuth\Storage\NoResourceServerStorage;
use fkooman\OAuth\Storage\NullApprovalStorage;
use fkooman\OAuth\Storage\NullAuthorizationCodeStorage;
use fkooman\OAuth\Storage\PdoAccessTokenStorage;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Form\FormAuthentication;
use fkooman\Rest\Plugin\Authentication\Mellon\MellonAuthentication;
use fkooman\Rest\Service;
use fkooman\Tpl\Twig\TwigTemplateManager;
use fkooman\VPN\UserPortal\DbTokenValidator;
use fkooman\VPN\UserPortal\UserTokens;
use fkooman\VPN\UserPortal\VootModule;
use fkooman\VPN\UserPortal\VpnApiModule;
use fkooman\VPN\UserPortal\VpnConfigApiClient;
use fkooman\VPN\UserPortal\VpnPortalModule;
use fkooman\VPN\UserPortal\VpnServerApiClient;
use GuzzleHttp\Client;

try {
    $request = new Request($_SERVER);

    // read the main configuration file
    $configReader = new Reader(new YamlFile(sprintf('%s/config/config.yaml', dirname(__DIR__))));
    $dataDir = $configReader->v('dataDir');
    $serverMode = $configReader->v('serverMode', false, 'production');

    $templateDirs = [
        sprintf('%s/views', dirname(__DIR__)),
        sprintf('%s/config/views', dirname(__DIR__)),
    ];

    // if in multi instance configuration, read the instance specific
    // configuration file and add instance specific template directory as well
    if ($configReader->v('multiInstance', false, false)) {
        $instanceId = $request->getUrl()->getHost();
        $configReader = new Reader(new YamlFile(sprintf('%s/config/%s/config.yaml', dirname(__DIR__), $instanceId)));
        $dataDir = sprintf('%s/%s', $dataDir, $instanceId);
        $templateDirs[] = sprintf('%s/config/%s/views', dirname(__DIR__), $instanceId);
    }

    $templateCache = null;
    if ('production' === $serverMode) {
        // enable template cache when running in production mode
        $templateCache = sprintf('%s/tpl', $dataDir);
    }

    $templateManager = new TwigTemplateManager($templateDirs, $templateCache);
    $templateManager->setDefault(
        array(
            'rootFolder' => $request->getUrl()->getRoot(),
            'rootUrl' => $request->getUrl()->getRootUrl(),
            'requestUrl' => $request->getUrl()->toString(),
        )
    );

    $session = new Session(
        'vpn-user-portal',
        array(
            'secure' => 'development' !== $serverMode,
        )
    );

    $activeLanguage = $session->get('activeLanguage');
    if (is_null($activeLanguage)) {
        $activeLanguage = 'en_US';
    }
    $templateManager->addDefault(
        [
            'activeLanguage' => $activeLanguage,
        ]
    );
    $templateManager->setI18n('VpnUserPortal', $activeLanguage, dirname(__DIR__).'/locale');

    // Authentication
    $authMethod = $configReader->v('authMethod');
    $templateManager->addDefault(array('authMethod' => $authMethod));

    switch ($authMethod) {
        case 'MellonAuthentication':
            $auth = new MellonAuthentication(
                $configReader->v('MellonAuthentication', 'attribute')
            );
            break;
        case 'FormAuthentication':
            $auth = new FormAuthentication(
                function ($userId) use ($configReader) {
                    $userList = $configReader->v('FormAuthentication');
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

    // vpn-ca-api
    $vpnConfigApiClient = new VpnConfigApiClient(
        new Client([
            'defaults' => [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $configReader->v('remoteApi', 'vpn-ca-api', 'token')),
                ],
            ],
        ]),
        $configReader->v('remoteApi', 'vpn-ca-api', 'uri')
    );

    // vpn-server-api
    $vpnServerApiClient = new VpnServerApiClient(
        new Client([
            'defaults' => [
                'auth' => ['vpn-user-portal', $configReader->v('remoteApi', 'vpn-server-api', 'token')]
            ],
        ]),
        $configReader->v('remoteApi', 'vpn-server-api', 'uri')
    );

    // check whether OAuth tokens DB exists, if not create it
    $dbFile = sprintf('%s/access_tokens.sqlite', $dataDir);
    $initDb = false;
    if (!file_exists($dbFile)) {
        @mkdir(dirname($dbFile), 0700, true);
        $initDb = true;
    }
    $accessTokensDb = new PDO(sprintf('sqlite://%s', $dbFile));
    $pdoAccessTokenStorage = new PdoAccessTokenStorage($accessTokensDb);
    if ($initDb) {
        $pdoAccessTokenStorage->initDatabase();
    }

    $vpnApiModule = new VpnApiModule(
        $vpnConfigApiClient,
        $vpnServerApiClient
    );

    $oauthModule = new OAuthModule(
        $templateManager,
        new ArrayClientStorage($configReader->v('apiClients', false, [])),
        new NoResourceServerStorage(),
        new NullApprovalStorage(),
        new NullAuthorizationCodeStorage(),
        $pdoAccessTokenStorage,
        [
            'disable_token_endpoint' => true,   // no need for token endpoint
            'disable_introspect_endpoint' => true, // no need for introspection
            'route_prefix' => '/_oauth',
        ]
    );

    $enableVoot = $configReader->v('enableVoot', false, false);
    if ($enableVoot) {
        $oauthClient = new OAuth2Client(
            new Provider(
                $configReader->v('Voot', 'clientId'),
                $configReader->v('Voot', 'clientSecret'),
                $configReader->v('Voot', 'authorizationEndpoint'),
                $configReader->v('Voot', 'tokenEndpoint')
            ),
            new CurlHttpClient()
        );
        $vootModule = new VootModule(
            $oauthClient,
            $vpnServerApiClient,
            $session
        );
    }

    $vpnPortalModule = new VpnPortalModule(
        $templateManager,
        $vpnConfigApiClient,
        $vpnServerApiClient,
        new UserTokens($accessTokensDb),
        $session
    );
    $vpnPortalModule->setUseVoot($enableVoot);

    $apiAuth = new BearerAuthentication(
        new DbTokenValidator($accessTokensDb),
        array(
            'realm' => 'VPN User API',
        )
    );

    $service = new Service();
    $service->addModule($vpnPortalModule);
    $service->addModule($vpnApiModule);
    $service->addModule($oauthModule);
    if ($enableVoot) {
        $service->addModule($vootModule);
    }

    $authenticationPlugin = new AuthenticationPlugin();
    $authenticationPlugin->register($auth, 'user');
    $authenticationPlugin->register(new UnauthenticatedClientAuthentication(), 'client');
    $authenticationPlugin->register($apiAuth, 'api');
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    $response = $service->run($request);
    $response->setHeader('Content-Security-Policy', "default-src 'self'");
    // X-Frame-Options: https://developer.mozilla.org/en-US/docs/HTTP/X-Frame-Options
    $response->setHeader('X-Frame-Options', 'DENY');
    $response->setHeader('X-Content-Type-Options', 'nosniff');
    $response->setHeader('X-Xss-Protection', '1; mode=block');
    $response->send();
} catch (Exception $e) {
    // internal server error
    error_log($e->__toString());
    $e = new InternalServerErrorException($e->getMessage());
    $e->getHtmlResponse()->send();
}
