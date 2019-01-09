<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\SodiumSigner;
use fkooman\SAML\SP\SP;
use fkooman\SAML\SP\SpInfo;
use fkooman\SAML\SP\XmlIdpInfoSource;
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
use SURFnet\VPN\Common\Http\LogoutModule;
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
use SURFnet\VPN\Common\Tpl;
use SURFnet\VPN\Portal\AdminHook;
use SURFnet\VPN\Portal\AdminPortalModule;
use SURFnet\VPN\Portal\ClientFetcher;
use SURFnet\VPN\Portal\DisabledUserHook;
use SURFnet\VPN\Portal\Graph;
use SURFnet\VPN\Portal\LastAuthenticatedAtPingHook;
use SURFnet\VPN\Portal\OAuthModule;
use SURFnet\VPN\Portal\OAuthStorage;
use SURFnet\VPN\Portal\PasswdModule;
use SURFnet\VPN\Portal\SamlAuthenticationHook;
use SURFnet\VPN\Portal\SamlModule;
use SURFnet\VPN\Portal\TwoFactorEnrollModule;
use SURFnet\VPN\Portal\VpnPortalModule;

$logger = new Logger('vpn-user-portal');

// on various systems we have various font locations
// XXX move this to configuration
$fontList = [
    '/usr/share/fonts/google-roboto/Roboto-Regular.ttf', // Fedora (google-roboto-fonts)
    '/usr/share/fonts/roboto_fontface/roboto/Roboto-Regular.ttf', // Fedora (roboto-fontface-fonts)
    '/usr/share/fonts/roboto_fontface/Roboto-Regular.ttf', // CentOS (roboto-fontface-fonts)
    '/usr/share/fonts-roboto-fontface/fonts/Roboto-Regular.ttf', // Debian (fonts-roboto-fontface)
];

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    if (false === $instanceId = getenv('VPN_INSTANCE_ID')) {
        $instanceId = $request->getServerName();
    }

    $dataDir = sprintf('%s/data/%s', $baseDir, $instanceId);
    FileIO::createDir($dataDir, 0700);

    $config = Config::fromFile(sprintf('%s/config/%s/config.php', $baseDir, $instanceId));

    $templateDirs = [
        sprintf('%s/views', $baseDir),
        sprintf('%s/config/%s/views', $baseDir, $instanceId),
    ];
    if ($config->hasItem('styleName')) {
        $templateDirs[] = sprintf('%s/views/%s', $baseDir, $config->getItem('styleName'));
    }

    // determine sessionExpiry, use the new configuration option if it is there
    // or fall back to Api 'refreshTokenExpiry', or "worst case" fall back to
    // hard coded 90 days
    if ($config->hasItem('sessionExpiry')) {
        $sessionExpiry = $config->getItem('sessionExpiry');
    } elseif ($config->getSection('Api')->hasItem('refreshTokenExpiry')) {
        $sessionExpiry = $config->getSection('Api')->getItem('refreshTokenExpiry');
    } else {
        $sessionExpiry = 'P90D';
    }

    // we always want browser session to expiry after PT8H hours, *EXCEPT* when
    // the configured "sessionExpiry" is < PT8H, then we want to follow that
    // setting...
    $browserSessionExpiry = 'PT8H';
    $dateTime = new DateTime();
    if (date_add(clone $dateTime, new DateInterval($browserSessionExpiry)) > date_add(clone $dateTime, new DateInterval($sessionExpiry))) {
        $browserSessionExpiry = $sessionExpiry;
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
            'SessionExpiry' => $browserSessionExpiry,
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

    $languageFile = null;
    if (array_key_exists('ui_lang', $_COOKIE)) {
        $uiLang = $_COOKIE['ui_lang'];
        if ('en_US' !== $uiLang) {
            $languageFile = sprintf('%s/locale/%s.php', $baseDir, $uiLang);
        }
    }
    $tpl = new Tpl($templateDirs, $languageFile);
    $tpl->addDefault(
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
            // since we now also support SAML / Mellon logout we *always* show
            // the logout button (except when showing the login page for
            // Form*Authentication (to remain backwards compatible with old
            // "base.twig")
            '_show_logout' => true,
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

    $logoutUrl = null;
    if ('MellonAuthentication' === $authMethod) {
        // mod_auth_mellon
        $logoutUrl = $request->getAuthority().'/saml/logout';
    }
    if ('SamlAuthentication' === $authMethod) {
        $logoutUrl = $request->getRootUri().'_saml/logout';
    }

    $service->addModule(new LogoutModule($session, $logoutUrl));
    switch ($authMethod) {
        case 'SamlAuthentication':
            $service->addBeforeHook(
                'auth',
                new SamlAuthenticationHook(
                    $session,
                    $config->getSection('SamlAuthentication')->getItem('attribute'),
                    $config->getSection('SamlAuthentication')->getItem('addEntityID'),
                    $config->getSection('SamlAuthentication')->optionalItem('entitlementAttribute')
                )
            );
            $spEntityId = $config->getSection('SamlAuthentication')->optionalItem('spEntityId', $request->getRootUri().'_saml/metadata');
            $service->addModule(
                new SamlModule(
                    $session,
                    new SP(
                        new SpInfo(
                            $spEntityId,
                            $request->getRootUri().'_saml/acs',
                            $request->getRootUri().'_saml/logout',
                            FileIO::readFile(sprintf('%s/config/%s/sp.key', $baseDir, $instanceId))
                        ),
                        new XmlIdpInfoSource($config->getSection('SamlAuthentication')->getItem('idpMetadata'))
                    ),
                    $config->getSection('SamlAuthentication')->optionalItem('idpEntityId'),
                    $config->getSection('SamlAuthentication')->optionalItem('discoUrl')
                )
            );

            break;
        case 'MellonAuthentication':
            $service->addBeforeHook(
                'auth',
                new MellonAuthenticationHook(
                    $session,
                    $config->getSection('MellonAuthentication')->getItem('attribute'),
                    $config->getSection('MellonAuthentication')->getItem('addEntityID'),
                    $config->getSection('MellonAuthentication')->optionalItem('entitlementAttribute')
                )
            );

            break;
        case 'FormLdapAuthentication':
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
                $config->getSection('FormLdapAuthentication')->getItem('userDnTemplate'),
                $config->getSection('FormLdapAuthentication')->optionalItem('entitlementAttribute')
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
            $userAuth = new PdoAuth(
                new PDO(
                    sprintf('sqlite://%s/data/%s/userdb.sqlite', $baseDir, $instanceId)
                )
            );

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

    $twoFactorMethods = $config->optionalItem('twoFactorMethods', ['totp']);
    if (0 !== count($twoFactorMethods)) {
        $service->addBeforeHook(
            'two_factor',
            new TwoFactorHook(
                $session,
                $tpl,
                $serverClient,
                $config->hasItem('requireTwoFactor') ? $config->getItem('requireTwoFactor') : false
            )
        );
    }

    $service->addBeforeHook('disabled_user', new DisabledUserHook($serverClient));
    $service->addBeforeHook('last_authenticated_at_ping', new LastAuthenticatedAtPingHook($session, $serverClient));

    // two factor module
    if (0 !== count($twoFactorMethods)) {
        $twoFactorModule = new TwoFactorModule($serverClient, $session, $tpl);
        $service->addModule($twoFactorModule);
    }

    // isAdmin
    $service->addBeforeHook(
        'is_admin',
        new AdminHook(
            $config->optionalItem('adminEntitlementList', ['admin']),
            $tpl
        )
    );

    // OAuth tokens
    $storage = new OAuthStorage(
        new PDO(sprintf('sqlite://%s/tokens.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir),
        $serverClient
    );
    $storage->update();

    $clientFetcher = new ClientFetcher($config);

    // portal module
    $vpnPortalModule = new VpnPortalModule(
        $config,
        $tpl,
        $serverClient,
        $session,
        $storage,
        new DateInterval($sessionExpiry),
        [$clientFetcher, 'get']
    );
    $service->addModule($vpnPortalModule);

    // admin module
    $graph = new Graph();
    $graph->setFontList($fontList);
    if ($config->hasSection('statsConfig')) {
        if ($config->getSection('statsConfig')->hasItem('barColor')) {
            $graph->setBarColor($config->getSection('statsConfig')->getItem('barColor'));
        }
    }

    $adminPortalModule = new AdminPortalModule(
        $tpl,
        $serverClient,
        $graph
    );
    $service->addModule($adminPortalModule);

    if (0 !== count($twoFactorMethods)) {
        $twoFactorEnrollModule = new TwoFactorEnrollModule($twoFactorMethods, $session, $tpl, $serverClient);
        $service->addModule($twoFactorEnrollModule);
    }

    // OAuth module
    if ($config->hasSection('Api')) {
        $oauthServer = new OAuthServer(
            $storage,
            [$clientFetcher, 'get'],
            new SodiumSigner(
                Base64::decode(
                    FileIO::readFile(
                        sprintf('%s/OAuth.key', $dataDir)
                    )
                )
            )
        );

        $oauthServer->setRefreshTokenExpiry(new DateInterval($sessionExpiry));
        $oauthServer->setAccessTokenExpiry(
            new DateInterval(
                $config->getSection('Api')->hasItem('tokenExpiry') ? sprintf('PT%dS', $config->getSection('Api')->getItem('tokenExpiry')) : 'PT1H'
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
