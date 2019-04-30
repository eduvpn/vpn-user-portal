<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\Jwt\Keys\EdDSA\SecretKey;
use fkooman\OAuth\Server\OAuthServer;
use fkooman\SAML\SP\PrivateKey;
use fkooman\SAML\SP\PublicKey;
use fkooman\SAML\SP\SP;
use fkooman\SAML\SP\SpInfo;
use fkooman\SAML\SP\XmlIdpInfoSource;
use fkooman\SeCookie\Cookie;
use fkooman\SeCookie\Session;
use LC\OpenVpn\ManagementSocket;
use LC\Portal\CA\EasyRsaCa;
use LC\Portal\ClientFetcher;
use LC\Portal\Config;
use LC\Portal\FileIO;
use LC\Portal\Graph;
use LC\Portal\Http\AdminHook;
use LC\Portal\Http\AdminPortalModule;
use LC\Portal\Http\CsrfProtectionHook;
use LC\Portal\Http\DisabledUserHook;
use LC\Portal\Http\FormAuthenticationHook;
use LC\Portal\Http\FormAuthenticationModule;
use LC\Portal\Http\HtmlResponse;
use LC\Portal\Http\LanguageSwitcherHook;
use LC\Portal\Http\LdapAuth;
use LC\Portal\Http\LogoutModule;
use LC\Portal\Http\MellonAuthenticationHook;
use LC\Portal\Http\OAuthModule;
use LC\Portal\Http\PasswdModule;
use LC\Portal\Http\RadiusAuth;
use LC\Portal\Http\Request;
use LC\Portal\Http\SamlAuthenticationHook;
use LC\Portal\Http\SamlModule;
use LC\Portal\Http\Service;
use LC\Portal\Http\ShibAuthenticationHook;
use LC\Portal\Http\TwoFactorEnrollModule;
use LC\Portal\Http\TwoFactorHook;
use LC\Portal\Http\TwoFactorModule;
use LC\Portal\Http\UpdateSessionInfoHook;
use LC\Portal\Http\VpnPortalModule;
use LC\Portal\LdapClient;
use LC\Portal\Logger;
use LC\Portal\OAuth\PublicSigner;
use LC\Portal\OpenVpn\ServerManager;
use LC\Portal\Storage;
use LC\Portal\TlsAuth;
use LC\Portal\Tpl;

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

    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir, 0700);

    $config = Config::fromFile(sprintf('%s/config/config.php', $baseDir));

    $templateDirs = [
        sprintf('%s/views', $baseDir),
        sprintf('%s/config/views', $baseDir),
    ];
    $styleConfig = null;
    if ($config->hasItem('styleName')) {
        $styleName = $config->getItem('styleName');
        $templateDirs[] = sprintf('%s/views/%s', $baseDir, $styleName);
        $styleConfig = Config::fromFile(sprintf('%s/config/%s.php', $baseDir, $styleName));
    }

    $sessionExpiry = $config->getItem('sessionExpiry');

    // we always want browser session to expiry after PT8H hours, *EXCEPT* when
    // the configured "sessionExpiry" is < PT8H, then we want to follow that
    // setting...
    $browserSessionExpiry = 'PT8H';
    $dateTime = new DateTime();
    if (date_add(clone $dateTime, new DateInterval($browserSessionExpiry)) > date_add(clone $dateTime, new DateInterval($sessionExpiry))) {
        $browserSessionExpiry = $sessionExpiry;
    }

    $secureCookie = $config->hasItem('secureCookie') ? $config->getItem('secureCookie') : true;

    $cookie = new Cookie(
        [
            'SameSite' => 'Lax',
            'Secure' => $secureCookie,
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
                // we can't set "SameSite" to Lax if we want to support the
                // SAML HTTP-POST binding...
                'SameSite' => null,
                'Secure' => $secureCookie,
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
        ]
    );

    $service = new Service($tpl);
    $service->addBeforeHook('csrf_protection', new CsrfProtectionHook());
    $service->addBeforeHook('language_switcher', new LanguageSwitcherHook(array_keys($supportedLanguages), $cookie));

    // Authentication
    $authMethod = $config->getItem('authMethod');

    $logoutUrl = null;
    $returnParameter = 'ReturnTo';
    if ('SamlAuthentication' === $authMethod) {
        $logoutUrl = $request->getRootUri().'_saml/logout';
    }
    if ('ShibAuthentication' === $authMethod) {
        $logoutUrl = $request->getAuthority().'/Shibboleth.sso/Logout';
        $returnParameter = 'return';
    }

    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir)
    );
    $storage->update();

    $service->addModule(new LogoutModule($session, $logoutUrl, $returnParameter));
    switch ($authMethod) {
        case 'SamlAuthentication':
            $spEntityId = $config->getSection('SamlAuthentication')->optionalItem('spEntityId', $request->getRootUri().'_saml/metadata');
            $serviceName = $config->getSection('SamlAuthentication')->optionalItem('serviceName', []);

            $userIdAttribute = $config->getSection('SamlAuthentication')->getItem('userIdAttribute');
            $permissionAttribute = $config->getSection('SamlAuthentication')->optionalItem('permissionAttribute');

            $spInfo = new SpInfo(
                $spEntityId,
                PrivateKey::fromFile(sprintf('%s/config/sp.key', $baseDir)),
                PublicKey::fromFile(sprintf('%s/config/sp.crt', $baseDir)),
                $request->getRootUri().'_saml/acs'
            );
            $spInfo->setSloUrl($request->getRootUri().'_saml/slo');
            $samlSp = new SP(
                $spInfo,
                new XmlIdpInfoSource($config->getSection('SamlAuthentication')->getItem('idpMetadata'))
            );
            $service->addBeforeHook(
                'auth',
                new SamlAuthenticationHook(
                    $samlSp,
                    $config->getSection('SamlAuthentication')->optionalItem('idpEntityId'),
                    $userIdAttribute,
                    $permissionAttribute,
                    $config->getSection('SamlAuthentication')->optionalItem('authnContext', []),
                    $config->getSection('SamlAuthentication')->optionalItem('permissionAuthnContext', []),
                    $config->getSection('SamlAuthentication')->optionalItem('permissionSessionExpiry', [])
                )
            );
            $service->addModule(
                new SamlModule(
                    $samlSp,
                    $config->getSection('SamlAuthentication')->optionalItem('discoUrl')
                )
            );

            break;
        case 'MellonAuthentication':
            $service->addBeforeHook(
                'auth',
                new MellonAuthenticationHook(
                    $config->getSection('MellonAuthentication')->getItem('userIdAttribute'),
                    $config->getSection('MellonAuthentication')->optionalItem('permissionAttribute')
                )
            );
            break;
        case 'ShibAuthentication':
            $service->addBeforeHook(
                'auth',
                new ShibAuthenticationHook(
                    $config->getSection('ShibAuthentication')->getItem('userIdAttribute'),
                    $config->getSection('ShibAuthentication')->optionalItem('permissionAttribute')
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
                $config->getSection('FormLdapAuthentication')->getItem('bindDnTemplate'),
                $config->getSection('FormLdapAuthentication')->optionalItem('baseDn'),
                $config->getSection('FormLdapAuthentication')->optionalItem('userFilterTemplate'),
                $config->getSection('FormLdapAuthentication')->optionalItem('permissionAttribute')
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
            $service->addBeforeHook(
                'auth',
                new FormAuthenticationHook(
                    $session,
                    $tpl
                )
            );

            $service->addModule(
                new FormAuthenticationModule(
                    $storage,
                    $session,
                    $tpl
                )
            );
            // add module for changing password
            $service->addModule(
                new PasswdModule(
                    $tpl,
                    $storage
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

            $serverList = $config->getSection('FormRadiusAuthentication')->getItem('serverList');
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
                $storage,
                $session,
                $tpl,
                $config->hasItem('requireTwoFactor') ? $config->getItem('requireTwoFactor') : false
            )
        );
    }

    $service->addBeforeHook('disabled_user', new DisabledUserHook($storage));
    $service->addBeforeHook('update_session_info', new UpdateSessionInfoHook($storage, $session, new DateInterval($sessionExpiry)));

    // two factor module
    if (0 !== count($twoFactorMethods)) {
        $twoFactorModule = new TwoFactorModule($storage, $session, $tpl);
        $service->addModule($twoFactorModule);
    }

    // isAdmin
    $service->addBeforeHook(
        'is_admin',
        new AdminHook(
            $config->optionalItem('adminPermissionList', []),
            $config->optionalItem('adminUserIdList', []),
            $tpl
        )
    );

    $easyRsaDir = sprintf('%s/easy-rsa', $baseDir);
    $easyRsaDataDir = sprintf('%s/easy-rsa', $dataDir);
    $easyRsaCa = new EasyRsaCa(
        $easyRsaDir,
        $easyRsaDataDir
    );
    $tlsAuth = new TlsAuth($dataDir);
    $serverManager = new ServerManager($config, $logger, new ManagementSocket());
    $clientFetcher = new ClientFetcher($config);

    // portal module
    $vpnPortalModule = new VpnPortalModule(
        $config,
        $tpl,
        $session,
        $storage,
        $easyRsaCa,
        $tlsAuth,
        $serverManager,
        $clientFetcher
    );
    $service->addModule($vpnPortalModule);

    // admin module
    $graph = new Graph();
    $graph->setFontList($fontList);
    if (null !== $styleConfig) {
        $graph->setBarColor($styleConfig->getItem('barColor'));
    }

    $adminPortalModule = new AdminPortalModule(
        $dataDir,
        $config,
        $tpl,
        $storage,
        $serverManager,
        $graph
    );
    $service->addModule($adminPortalModule);

    if (0 !== count($twoFactorMethods)) {
        $twoFactorEnrollModule = new TwoFactorEnrollModule($storage, $twoFactorMethods, $session, $tpl);
        $service->addModule($twoFactorEnrollModule);
    }

    // OAuth module
    $secretKey = SecretKey::fromEncodedString(
        FileIO::readFile(
            sprintf('%s/config/oauth.key', $baseDir)
        )
    );
    if ($config->hasSection('Api')) {
        $oauthServer = new OAuthServer(
            $storage,
            $clientFetcher,
            new PublicSigner($secretKey->getPublicKey(), $secretKey)
        );

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
