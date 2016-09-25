<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use GuzzleHttp\Client;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\HttpClient\CaClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Http\FormAuthenticationHook;
use SURFnet\VPN\Common\Http\FormAuthenticationModule;
use SURFnet\VPN\Common\Http\MellonAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\SecurityHeadersHook;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\HtmlResponse;
use SURFnet\VPN\Common\Http\Session;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Portal\TwigTpl;
use SURFnet\VPN\Portal\GuzzleHttpClient;
use SURFnet\VPN\Portal\VpnPortalModule;
use SURFnet\VPN\Portal\VootModule;
use SURFnet\VPN\Portal\OtpModule;
use SURFnet\VPN\Portal\LanguageSwitcherHook;
use fkooman\OAuth\Client\OAuth2Client;
use fkooman\OAuth\Client\Provider;
use fkooman\OAuth\Client\CurlHttpClient;
use SURFnet\VPN\Common\Http\ReferrerCheckHook;
use SURFnet\VPN\Portal\OAuth\OAuthModule;
use SURFnet\VPN\Portal\OAuth\Random;
use SURFnet\VPN\Portal\OAuth\TokenStorage;

$logger = new Logger('vpn-user-portal');

try {
    $request = new Request($_SERVER, $_GET, $_POST);
    $instanceId = $request->getServerName();

    $dataDir = sprintf('%s/data/%s', dirname(__DIR__), $instanceId);
    $config = Config::fromFile(sprintf('%s/config/%s/config.yaml', dirname(__DIR__), $instanceId));

    $templateDirs = [
        sprintf('%s/views', dirname(__DIR__)),
        sprintf('%s/config/%s/views', dirname(__DIR__), $instanceId),
    ];
    $serverMode = $config->v('serverMode');

    $templateCache = null;
    if ('production' === $serverMode) {
        // enable template cache when running in production mode
        $templateCache = sprintf('%s/tpl', $dataDir);
    }

    $tpl = new TwigTpl($templateDirs, $templateCache);
    $tpl->setDefault(
        array(
            'requestUri' => $request->getUri(),
            'requestRoot' => $request->getRoot(),
            'requestRootUri' => $request->getRootUri(),
        )
    );

    $session = new Session(
        'vpn-user-portal',
        array(
            'secure' => 'development' !== $serverMode,
        )
    );

    $supportedLanguages = $config->v('supportedLanguages');
    $activeLanguage = $session->get('activeLanguage');
    if (is_null($activeLanguage)) {
        $activeLanguage = array_keys($supportedLanguages)[0];
    }

    $tpl->addDefault(
        [
            'supportedLanguages' => $supportedLanguages,
            'activeLanguage' => $activeLanguage,
        ]
    );
    $tpl->setI18n('VpnUserPortal', $activeLanguage, dirname(__DIR__).'/locale');

    $service = new Service($tpl);
    $service->addBeforeHook('referrer_check', new ReferrerCheckHook());
    $service->addAfterHook('security_headers', new SecurityHeadersHook());
    $service->addBeforeHook('language_switcher', new LanguageSwitcherHook($session, array_keys($supportedLanguages)));

    // Authentication
    $authMethod = $config->v('authMethod');
    $tpl->addDefault(array('authMethod' => $authMethod));

    switch ($authMethod) {
        case 'MellonAuthentication':
            $service->addBeforeHook(
                'auth',
                new MellonAuthenticationHook(
                    $config->v('MellonAuthentication', 'attribute')
                )
            );
            break;
        case 'FormAuthentication':
            $service->addBeforeHook(
                'auth',
                new FormAuthenticationHook(
                    $session,
                    $tpl
                )
            );
            $service->addModule(
                new FormAuthenticationModule(
                    $config->v('FormAuthentication'),
                    $session,
                    $tpl
                )
            );
            break;
        default:
            throw new RuntimeException('unsupported authentication mechanism');
    }

    // vpn-ca-api
    $guzzleCaClient = new GuzzleHttpClient(
        new Client([
            'defaults' => [
                'auth' => [
                    $config->v('apiProviders', 'vpn-ca-api', 'userName'),
                    $config->v('apiProviders', 'vpn-ca-api', 'userPass'),
                ],
            ],
        ])
    );
    $caClient = new CaClient($guzzleCaClient, $config->v('apiProviders', 'vpn-ca-api', 'apiUri'));

    // vpn-server-api
    $guzzleServerClient = new GuzzleHttpClient(
        new Client([
            'defaults' => [
                'auth' => [
                    $config->v('apiProviders', 'vpn-server-api', 'userName'),
                    $config->v('apiProviders', 'vpn-server-api', 'userPass'),
                ],
            ],
        ])
    );
    $serverClient = new ServerClient($guzzleServerClient, $config->v('apiProviders', 'vpn-server-api', 'apiUri'));

    // portal module
    $vpnPortalModule = new VpnPortalModule(
        $tpl,
        $serverClient,
        $caClient,
        $session
    );
    $service->addModule($vpnPortalModule);

    // otp module
    $otpModule = new OtpModule(
        $tpl,
        $serverClient
    );
    $service->addModule($otpModule);

    // oauth module
    if ($config->v('enableOAuth')) {
        if (!file_exists($dataDir)) {
            if (false === @mkdir($dataDir, 0700, true)) {
                throw new RuntimeException(sprintf('unable to create folder "%s"', $dataDir));
            }
        }
        $tokenStorage = new TokenStorage(new PDO(sprintf('sqlite://%s/tokens.sqlite', $dataDir)));
        $tokenStorage->init();

        $oauthModule = new OAuthModule(
            $tpl,
            new Random(),
            $tokenStorage,
            $config
        );
        $service->addModule($oauthModule);
    }

    // voot module
    if ($config->v('enableVoot')) {
        $vootModule = new VootModule(
                new OAuth2Client(
                new Provider(
                    $config->v('Voot', 'clientId'),
                    $config->v('Voot', 'clientSecret'),
                    $config->v('Voot', 'authorizationEndpoint'),
                    $config->v('Voot', 'tokenEndpoint')
                ),
                new CurlHttpClient()
            ),
            $serverClient,
            $session
        );
        $service->addModule($vootModule);
    }

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new HtmlResponse($e->getMessage(), 500);
    $response->send();
}
