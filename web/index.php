<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

$baseDir = dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once sprintf('%s/vendor/autoload.php', $baseDir);

use fkooman\OAuth\Client\Http\CurlHttpClient as OAuthCurlHttpClient;
use fkooman\OAuth\Client\OAuthClient;
use fkooman\OAuth\Client\Provider;
use fkooman\OAuth\Server\ClientInfo;
use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\SodiumSigner;
use fkooman\OAuth\Server\Storage;
use fkooman\SeCookie\Cookie;
use fkooman\SeCookie\Session;
use ParagonIE\ConstantTime\Base64;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Common\Http\CsrfProtectionHook;
use SURFnet\VPN\Common\Http\FormAuthenticationHook;
use SURFnet\VPN\Common\Http\FormAuthenticationModule;
use SURFnet\VPN\Common\Http\HtmlResponse;
use SURFnet\VPN\Common\Http\LanguageSwitcherHook;
use SURFnet\VPN\Common\Http\LdapAuth;
use SURFnet\VPN\Common\Http\MellonAuthenticationHook;
use SURFnet\VPN\Common\Http\PdoAuth;
use SURFnet\VPN\Common\Http\RadiusAuth;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\SimpleAuth;
use SURFnet\VPN\Common\Http\TwoFactorHook;
use SURFnet\VPN\Common\Http\TwoFactorModule;
use SURFnet\VPN\Common\HttpClient\CurlHttpClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\LdapClient;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Common\TwigTpl;
use SURFnet\VPN\Portal\DisabledUserHook;
use SURFnet\VPN\Portal\OAuthClientInfo;
use SURFnet\VPN\Portal\OAuthModule;
use SURFnet\VPN\Portal\PasswdModule;
use SURFnet\VPN\Portal\RegistrationHook;
use SURFnet\VPN\Portal\TotpModule;
use SURFnet\VPN\Portal\VootModule;
use SURFnet\VPN\Portal\VootTokenHook;
use SURFnet\VPN\Portal\VootTokenStorage;
use SURFnet\VPN\Portal\Voucher;
use SURFnet\VPN\Portal\VpnPortalModule;
use SURFnet\VPN\Portal\YubiModule;

$logger = new Logger('vpn-user-portal');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    if (false === $instanceId = getenv('VPN_INSTANCE_ID')) {
        $instanceId = $request->getServerName();
    }

    $dataDir = sprintf('%s/data/%s', $baseDir, $instanceId);
    if (!file_exists($dataDir)) {
        if (false === @mkdir($dataDir, 0700, true)) {
            throw new RuntimeException(sprintf('unable to create folder "%s"', $dataDir));
        }
    }

    $config = Config::fromFile(sprintf('%s/config/%s/config.php', $baseDir, $instanceId));

    $templateDirs = [
        sprintf('%s/views', $baseDir),
        sprintf('%s/config/%s/views', $baseDir, $instanceId),
    ];
    if ($config->hasItem('styleName')) {
        $templateDirs[] = sprintf('%s/views/%s', $baseDir, $config->getItem('styleName'));
    }

    $templateCache = null;
    if ($config->getItem('enableTemplateCache')) {
        $templateCache = sprintf('%s/tpl', $dataDir);
    }

    $cookie = new Cookie(
        [
            'SameSite' => 'Lax',
            'Secure' => $config->getItem('secureCookie'),
            'Max-Age' => 60 * 60 * 24 * 90,   // 90 days
        ]
    );

    $session = new Session(
        [
            'SessionName' => 'SID',
            'DomainBinding' => $request->getServerName(),
            'PathBinding' => $request->getRoot(),
        ],
        new Cookie(
            [
                // we need to bind to "Path", otherwise the (Basic)
                // authentication mechanism will set a cookie for
                // {ROOT}/_form/auth/
                'Path' => $request->getRoot(),
                'SameSite' => 'Lax',
                'Secure' => $config->getItem('secureCookie'),
            ]
        )
    );

    $tpl = new TwigTpl($templateDirs, $baseDir.'/locale', 'VpnUserPortal', $templateCache);
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
        case 'FormLdapAuthentication':
            $tpl->addDefault(['_show_logout' => true]);
            $service->addBeforeHook(
                'auth',
                new FormAuthenticationHook(
                    $session,
                    $tpl
                )
            );
            $ldapClient = new LdapClient(
                $config->getSection('FormLdapAuthentication')->getItem('ldapUri')
            );
            $userAuth = new LdapAuth(
                $logger,
                $ldapClient,
                $config->getSection('FormLdapAuthentication')->getItem('userDnTemplate')
            );
            $service->addModule(
                new FormAuthenticationModule(
                    $userAuth,
                    $session,
                    $tpl
                )
            );

            break;
        case 'FormPdoAuthentication':
            $tpl->addDefault(['_show_logout' => true]);

            $userAuth = new PdoAuth(
                new PDO(
                    sprintf('sqlite://%s/data/%s/userdb.sqlite', $baseDir, $instanceId)
                )
            );

            // if we allow registration, enable that module as well, but
            // before the authentication module as to not require to authenticate
            // before registration can take place
            if ($config->getSection('FormPdoAuthentication')->getItem('allowRegistration')) {
                $voucher = new Voucher(
                    new PDO(
                        sprintf('sqlite://%s/data/%s/vouchers.sqlite', $baseDir, $instanceId)
                    )
                );

                // registration enabled
                $service->addBeforeHook(
                    'registration',
                    new RegistrationHook(
                        $tpl,
                        $userAuth,
                        $voucher
                    )
                );
            }

            $service->addBeforeHook(
                'auth',
                new FormAuthenticationHook(
                    $session,
                    $tpl
                )
            );

            $service->addModule(
                new FormAuthenticationModule(
                    $userAuth,
                    $session,
                    $tpl
                )
            );
            // add module for changing password
            $service->addModule(
                new PasswdModule(
                    $tpl,
                    $userAuth
                )
            );

            break;
        case 'FormRadiusAuthentication':
            $tpl->addDefault(['_show_logout' => true]);
            $service->addBeforeHook(
                'auth',
                new FormAuthenticationHook(
                    $session,
                    $tpl
                )
            );

            if ($config->getSection('FormRadiusAuthentication')->hasItem('serverList')) {
                $serverList = $config->getSection('FormRadiusAuthentication')->getItem('serverList');
            } else {
                // legacy way of configuring RADIUS servers, only one specified here
                // XXX remove for 2.0
                $serverList = [
                    [
                        'host' => $config->getSection('FormRadiusAuthentication')->getItem('host'),
                        'secret' => $config->getSection('FormRadiusAuthentication')->getItem('secret'),
$config->getSection('FormRadiusAuthentication')->hasItem('port') ? $config->getSection('FormRadiusAuthentication')->getItem('port') : 1812,
                    ],
                ];
            }

            $userAuth = new RadiusAuth($logger, $serverList);
            if ($config->getSection('FormRadiusAuthentication')->hasItem('addRealm')) {
                $userAuth->setRealm($config->getSection('FormRadiusAuthentication')->getItem('addRealm'));
            }
            if ($config->getSection('FormRadiusAuthentication')->hasItem('nasIdentifier')) {
                $userAuth->setNasIdentifier($config->getSection('FormRadiusAuthentication')->getItem('nasIdentifier'));
            }

            $service->addModule(
                new FormAuthenticationModule(
                    $userAuth,
                    $session,
                    $tpl
                )
            );

            break;
        case 'FormAuthentication':
            // XXX remove for 2.0
            $tpl->addDefault(['_show_logout' => true]);
            $service->addBeforeHook(
                'auth',
                new FormAuthenticationHook(
                    $session,
                    $tpl
                )
            );
            $userAuth = new SimpleAuth(
                $config->getSection('FormAuthentication')->toArray()
            );
            $service->addModule(
                new FormAuthenticationModule(
                    $userAuth,
                    $session,
                    $tpl
                )
            );

            break;
        default:
            throw new RuntimeException('unsupported authentication mechanism');
    }

    $tpl->addDefault(
        [
            'authMethod' => $authMethod,
        ]
    );

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
        $provider = new Provider(
            $config->getSection('Voot')->getItem('clientId'),
            $config->getSection('Voot')->getItem('clientSecret'),
            $config->getSection('Voot')->getItem('authorizationEndpoint'),
            $config->getSection('Voot')->getItem('tokenEndpoint')
        );
        $vootModule = new VootModule(
            $oauthClient,
            $provider,
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
            // if not in configuration file, check if it is in the hardcoded list
            return OAuthClientInfo::getClient($clientId);
        }

        // XXX switch to only support 'redirect_uri_list' for 2.0
        $clientInfoData = $config->getSection('Api')->getSection('consumerList')->getItem($clientId);
        $redirectUriList = [];
        if (array_key_exists('redirect_uri_list', $clientInfoData)) {
            $redirectUriList = array_merge($redirectUriList, (array) $clientInfoData['redirect_uri_list']);
        }
        if (array_key_exists('redirect_uri', $clientInfoData)) {
            $redirectUriList = array_merge($redirectUriList, (array) $clientInfoData['redirect_uri']);
        }
        $clientInfoData['redirect_uri_list'] = $redirectUriList;

        return new ClientInfo($clientInfoData);
    };

    // portal module
    $vpnPortalModule = new VpnPortalModule(
        $tpl,
        $serverClient,
        $session,
        $storage,
        $getClientInfo
    );
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
            new SodiumSigner(
                Base64::decode(
                    FileIO::readFile(
                        sprintf('%s/OAuth.key', $dataDir)
                    )
                )
            )
        );

        $oauthServer->setRefreshTokenExpiry(
            new DateInterval(
                $config->getSection('Api')->hasItem('refreshTokenExpiry') ? $config->getSection('Api')->getItem('refreshTokenExpiry') : 'P1Y'
            )
        );
        $oauthServer->setAccessTokenExpiry(
            new DateInterval(
                sprintf('PT%dS', $config->getSection('Api')->getItem('tokenExpiry'))
            )
        );

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
