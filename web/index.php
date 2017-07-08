<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use fkooman\OAuth\Client\Http\CurlHttpClient as OAuthCurlHttpClient;
use fkooman\OAuth\Client\OAuthClient;
use fkooman\OAuth\Client\Provider;
use fkooman\OAuth\Server\ClientInfo;
use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\Storage;
use fkooman\SeCookie\Cookie;
use fkooman\SeCookie\Session;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Common\Http\CsrfProtectionHook;
use SURFnet\VPN\Common\Http\FormAuthenticationHook;
use SURFnet\VPN\Common\Http\FormAuthenticationModule;
use SURFnet\VPN\Common\Http\HtmlResponse;
use SURFnet\VPN\Common\Http\LanguageSwitcherHook;
use SURFnet\VPN\Common\Http\MellonAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\TwoFactorHook;
use SURFnet\VPN\Common\Http\TwoFactorModule;
use SURFnet\VPN\Common\HttpClient\CurlHttpClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Common\TwigTpl;
use SURFnet\VPN\Portal\DisabledUserHook;
use SURFnet\VPN\Portal\OAuthModule;
use SURFnet\VPN\Portal\TotpModule;
use SURFnet\VPN\Portal\VootModule;
use SURFnet\VPN\Portal\VootTokenHook;
use SURFnet\VPN\Portal\VootTokenStorage;
use SURFnet\VPN\Portal\VpnPortalModule;
use SURFnet\VPN\Portal\YubiModule;

$logger = new Logger('vpn-user-portal');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    if (false === $instanceId = getenv('VPN_INSTANCE_ID')) {
        $instanceId = $request->getServerName();
    }

    $dataDir = sprintf('%s/data/%s', dirname(__DIR__), $instanceId);
    if (!file_exists($dataDir)) {
        if (false === @mkdir($dataDir, 0700, true)) {
            throw new RuntimeException(sprintf('unable to create folder "%s"', $dataDir));
        }
    }

    $config = Config::fromFile(sprintf('%s/config/%s/config.php', dirname(__DIR__), $instanceId));

    $templateDirs = [
        sprintf('%s/views', dirname(__DIR__)),
        sprintf('%s/config/%s/views', dirname(__DIR__), $instanceId),
    ];

    $templateCache = null;
    if ($config->getItem('enableTemplateCache')) {
        $templateCache = sprintf('%s/tpl', $dataDir);
    }

    $session = new Session(
        [
            'SameSite' => 'Lax',
            'Path' => $request->getRoot(),
            'DomainBinding' => $request->getServerName(),
            'PathBinding' => $request->getRoot(),
            'Secure' => $config->getItem('secureCookie'),
        ]
    );

    $cookie = new Cookie(
        [
            'SameSite' => 'Lax',
            'Secure' => $config->getItem('secureCookie'),
            'Max-Age' => 60 * 60 * 24 * 90,   // 90 days
            'Path' => $request->getRoot(),
            'Domain' => $request->getServerName(),
        ]
    );

    $tpl = new TwigTpl($templateDirs, dirname(__DIR__).'/locale', 'VpnUserPortal', $templateCache);
    $tpl->setDefault(
        [
            'requestUri' => $request->getUri(),
            'requestRoot' => $request->getRoot(),
            'requestRootUri' => $request->getRootUri(),
        ]
    );
    $supportedLanguages = $config->getSection('supportedLanguages')->toArray();
    $tpl->addDefault(
        [
            'supportedLanguages' => $supportedLanguages,
        ]
    );

    $serverClient = new ServerClient(
        new CurlHttpClient([$config->getItem('apiUser'), $config->getItem('apiPass')]),
        $config->getItem('apiUri')
    );

    $service = new Service($tpl);
    $service->addBeforeHook('csrf_protection', new CsrfProtectionHook());
    $service->addBeforeHook('language_switcher', new LanguageSwitcherHook(array_keys($supportedLanguages), $cookie));

    // Authentication
    $authMethod = $config->getItem('authMethod');

    switch ($authMethod) {
        case 'MellonAuthentication':
            $service->addBeforeHook(
                'auth',
                new MellonAuthenticationHook(
                    $session,
                    $config->getSection('MellonAuthentication')->getItem('attribute'),
                    $config->getSection('MellonAuthentication')->getItem('addEntityID')
                )
            );
            break;
        case 'FormAuthentication':
            $tpl->addDefault(['_show_logout' => true]);
            $service->addBeforeHook(
                'auth',
                new FormAuthenticationHook(
                    $session,
                    $tpl
                )
            );
            $service->addModule(
                new FormAuthenticationModule(
                    $config->getSection('FormAuthentication')->toArray(),
                    $session,
                    $tpl
                )
            );
            break;
        default:
            throw new RuntimeException('unsupported authentication mechanism');
    }

    $service->addBeforeHook('disabled_user', new DisabledUserHook($serverClient));
    $service->addBeforeHook('two_factor', new TwoFactorHook($session, $tpl, $serverClient));

    // two factor module
    $twoFactorModule = new TwoFactorModule($serverClient, $session, $tpl);
    $service->addModule($twoFactorModule);

    // voot module
    if ($config->getItem('enableVoot')) {
        $service->addBeforeHook('voot_token', new VootTokenHook($serverClient));
        $oauthClient = new OAuthClient(
            new VootTokenStorage($serverClient),
            new OAuthCurlHttpClient()
        );
        $oauthClient->setProvider(
            new Provider(
                $config->getSection('Voot')->getItem('clientId'),
                $config->getSection('Voot')->getItem('clientSecret'),
                $config->getSection('Voot')->getItem('authorizationEndpoint'),
                $config->getSection('Voot')->getItem('tokenEndpoint')
            )
        );
        $vootModule = new VootModule(
            $oauthClient,
            $serverClient,
            $session
        );
        $service->addModule($vootModule);
    }

    // OAuth tokens
    $storage = new Storage(new PDO(sprintf('sqlite://%s/tokens.sqlite', $dataDir)));
    $storage->init();

    $getClientInfo = function ($clientId) use ($config) {
        if (false === $config->getSection('Api')->getSection('consumerList')->hasItem($clientId)) {
            return false;
        }

        return new ClientInfo($config->getSection('Api')->getSection('consumerList')->getItem($clientId));
    };

    // portal module
    $vpnPortalModule = new VpnPortalModule(
        $tpl,
        $serverClient,
        $session,
        $storage,
        $getClientInfo
    );
    if ($config->hasItem('addVpnProtoPorts')) {
        $vpnPortalModule->setAddVpnProtoPorts($config->getItem('addVpnProtoPorts'));
    }

    $service->addModule($vpnPortalModule);

    // TOTP module
    $totpModule = new TotpModule(
        $tpl,
        $serverClient
    );
    $service->addModule($totpModule);

    // Yubi module
    $yubiModule = new YubiModule(
        $tpl,
        $serverClient
    );
    $service->addModule($yubiModule);

    // OAuth module
    if ($config->hasSection('Api')) {
        $oauthServer = new OAuthServer(
            $storage,
            $getClientInfo,
            FileIO::readFile(sprintf('%s/OAuth.key', $dataDir))
        );
        $oauthServer->setExpiresIn($config->getSection('Api')->getItem('tokenExpiry'));
        $oauthModule = new OAuthModule(
            $tpl,
            $oauthServer
        );
        $service->addModule($oauthModule);
    }

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new HtmlResponse($e->getMessage(), 500);
    $response->send();
}
