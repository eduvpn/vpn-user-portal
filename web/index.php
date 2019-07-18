<?php

declare(strict_types=1);

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
use fkooman\SeCookie\Cookie;
use fkooman\SeCookie\CookieOptions;
use fkooman\SeCookie\Session;
use fkooman\SeCookie\SessionOptions;
use LC\OpenVpn\ManagementSocket;
use LC\Portal\CA\EasyRsaCa;
use LC\Portal\Config\PortalConfig;
use LC\Portal\FileIO;
use LC\Portal\Http\AdminHook;
use LC\Portal\Http\AdminPortalModule;
use LC\Portal\Http\CsrfProtectionHook;
use LC\Portal\Http\DisabledUserHook;
use LC\Portal\Http\FormAuthenticationHook;
use LC\Portal\Http\FormAuthenticationModule;
use LC\Portal\Http\HtmlResponse;
use LC\Portal\Http\InputValidation;
use LC\Portal\Http\LanguageSwitcherHook;
use LC\Portal\Http\LdapAuth;
use LC\Portal\Http\LogoutModule;
use LC\Portal\Http\OAuthModule;
use LC\Portal\Http\PasswdModule;
use LC\Portal\Http\RadiusAuth;
use LC\Portal\Http\Request;
use LC\Portal\Http\SamlModule;
use LC\Portal\Http\Service;
use LC\Portal\Http\TwoFactorEnrollModule;
use LC\Portal\Http\TwoFactorHook;
use LC\Portal\Http\TwoFactorModule;
use LC\Portal\Http\UpdateSessionInfoHook;
use LC\Portal\Http\VpnPortalModule;
use LC\Portal\Init;
use LC\Portal\LdapClient;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OAuth\PublicSigner;
use LC\Portal\OpenVpn\ServerManager;
use LC\Portal\OpenVpn\TlsCrypt;
use LC\Portal\Storage;
use LC\Portal\Tpl;
use Psr\Log\NullLogger;

$logger = new NullLogger();

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    $configDir = sprintf('%s/config', $baseDir);

    $init = new Init($baseDir);
    $init->init();

    $portalConfig = PortalConfig::fromFile(sprintf('%s/config.php', $configDir));

    $templateDirs = [
        sprintf('%s/views', $baseDir),
        sprintf('%s/views', $configDir),
    ];

    if (null !== $styleName = $portalConfig->getStyleName()) {
        $templateDirs[] = sprintf('%s/views/%s', $baseDir, $styleName);
    }

    $sessionExpiry = $portalConfig->getSessionExpiry();

    // we always want browser session to expiry after PT8H hours, *EXCEPT* when
    // the configured "sessionExpiry" is < PT8H, then we want to follow that
    // setting...
    $browserSessionExpiry = 'PT8H';
    $dateTime = new DateTime();
    if (date_add(clone $dateTime, new DateInterval($browserSessionExpiry)) > date_add(clone $dateTime, $sessionExpiry)) {
        $browserSessionExpiry = $sessionExpiry;
    }

    $secureCookie = $portalConfig->getSecureCookie();

    $cookieOptions = CookieOptions::init()->setSameSite('Strict')->setSecure($secureCookie)->setMaxAge(60 * 60 * 24 * 90);
    $cookie = new Cookie($cookieOptions);

    // the Application session cookie has SameSite=Strict, only direct
    // navigation should send cookies...
    $sessionCookieOptions = CookieOptions::init()->setSameSite('Strict')->setSecure($secureCookie)->setPath($request->getRoot());
    $sessionOptions = $sessionOptions = SessionOptions::init();
    $sessionOptions->cookieOptions = $sessionCookieOptions;
    $session = new Session('SID', $sessionOptions);
    $session->start();

    // the SAML session cookie has no SameSite attribute, as that would break
    // when accepting SAML responses on the ACS
    $samlSessionCookieOptions = CookieOptions::init()->setSameSite(null)->setSecure($secureCookie)->setPath($request->getRoot().'_saml');
    $samlSessionOptions = SessionOptions::init();
    $samlSessionOptions->cookieOptions = $samlSessionCookieOptions;
    $samlSession = new Session('SAML_SID', $samlSessionOptions);

    $supportedLanguages = $portalConfig->getSupportedLanguages();
    // the first listed language is the default language
    $uiLang = array_keys($supportedLanguages)[0];
    $languageFile = null;
    if ($cookie->has('ui_lang')) {
        $uiLang = InputValidation::uiLang($cookie->get('ui_lang'));
    }
    if ('en_US' !== $uiLang) {
        if (array_key_exists($uiLang, $supportedLanguages)) {
            $languageFile = sprintf('%s/locale/%s.php', $baseDir, $uiLang);
        }
    }

    $tpl = new Tpl($templateDirs, $languageFile);
    $tpl->addDefault(
        [
            'requestRoot' => $request->getRoot(),
        ]
    );
    $tpl->addDefault(
        [
            'supportedLanguages' => $supportedLanguages,
        ]
    );

    $service = new Service($tpl);
    $service->addBeforeHook('csrf_protection', new CsrfProtectionHook());
    $service->addBeforeHook('language_switcher', new LanguageSwitcherHook(array_keys($supportedLanguages), $cookie));

    // Authentication
    $authMethod = $portalConfig->getAuthMethod();

    $logoutUrl = null;
    $returnParameter = 'ReturnTo';
    if ('SamlAuthentication' === $authMethod) {
        $logoutUrl = $request->getRootUri().'_saml/logout';
    }

    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir)
    );

    $service->addModule(new LogoutModule($session, $logoutUrl, $returnParameter));
    switch ($authMethod) {
        case 'DbAuthentication':
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
        case 'SamlAuthentication':
            $samlModule = new SamlModule(
                $configDir,
                $portalConfig->getSamlAuthenticationConfig(),
                $samlSession
            );
            $service->addBeforeHook('auth', $samlModule);
            $service->addModule($samlModule);

            break;
        case 'LdapAuthentication':
            $ldapAuthenticationConfig = $portalConfig->getLdapAuthenticationConfig();
            $service->addBeforeHook(
                'auth',
                new FormAuthenticationHook(
                    $session,
                    $tpl
                )
            );
            $ldapClient = new LdapClient(
                $ldapAuthenticationConfig->getLdapUri()
            );
            $userAuth = new LdapAuth(
                $logger,
                $ldapClient,
                $ldapAuthenticationConfig->getBindDnTemplate(),
                $ldapAuthenticationConfig->getBaseDn(),
                $ldapAuthenticationConfig->getUserFilterTemplate(),
                $ldapAuthenticationConfig->getPermissionAttributeList()
            );
            $service->addModule(
                new FormAuthenticationModule(
                    $userAuth,
                    $session,
                    $tpl
                )
            );

            break;
// XXX fix the config for this auth module
//        case 'FormADLdapAuthentication':
//            $service->addBeforeHook(
//                'auth',
//                new FormAuthenticationHook(
//                    $session,
//                    $tpl
//                )
//            );
//            $ldapClient = new LdapClient(
//                $config->getSection('FormADLdapAuthentication')->getItem('ldapUri')
//            );
//            $userAuth = new ADLdapAuth(
//                $logger,
//                $ldapClient,
//                $config->getSection('FormADLdapAuthentication')->getItem('bindDnTemplate'),
//                $config->getSection('FormADLdapAuthentication')->optionalItem('baseDn'),
//                $config->getSection('FormADLdapAuthentication')->optionalItem('permissionMemberships')
//            );
//            $service->addModule(
//                new FormAuthenticationModule(
//                    $userAuth,
//                    $session,
//                    $tpl
//                )
//            );

//            break;
        case 'RadiusAuthentication':
            $radiusAuthenticationConfig = $portalConfig->getRadiusAuthenticationConfig();
            $service->addBeforeHook(
                'auth',
                new FormAuthenticationHook(
                    $session,
                    $tpl
                )
            );
            $userAuth = new RadiusAuth(
                $logger,
                $radiusAuthenticationConfig
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

    $twoFactorMethods = $portalConfig->getTwoFactorMethods();
    if (0 !== count($twoFactorMethods)) {
        $service->addBeforeHook(
            'two_factor',
            new TwoFactorHook(
                $storage,
                $session,
                $tpl,
                $portalConfig->getRequireTwoFactor()
            )
        );
    }

    $service->addBeforeHook('disabled_user', new DisabledUserHook($storage));
    $service->addBeforeHook('update_session_info', new UpdateSessionInfoHook($storage, $session, $sessionExpiry));

    // two factor module
    if (0 !== count($twoFactorMethods)) {
        $twoFactorModule = new TwoFactorModule($storage, $session, $tpl);
        $service->addModule($twoFactorModule);
    }

    // isAdmin
    $service->addBeforeHook(
        'is_admin',
        new AdminHook(
            $portalConfig->getAdminPermissionList(),
            $portalConfig->getAdminUserIdList(),
            $tpl
        )
    );

    $easyRsaDir = sprintf('%s/easy-rsa', $baseDir);
    $easyRsaDataDir = sprintf('%s/easy-rsa', $dataDir);
    $easyRsaCa = new EasyRsaCa(
        $easyRsaDir,
        $easyRsaDataDir
    );
    $tlsCrypt = TlsCrypt::fromFile(sprintf('%s/tls-crypt.key', $dataDir));
    $serverManager = new ServerManager($portalConfig, new ManagementSocket());
    $serverManager->setLogger($logger);
    $clientDb = new clientDb();

    // portal module
    $vpnPortalModule = new VpnPortalModule(
        $portalConfig,
        $tpl,
        $session,
        $storage,
        $easyRsaCa,
        $tlsCrypt,
        $serverManager,
        $clientDb
    );
    $service->addModule($vpnPortalModule);

    // admin module
    $adminPortalModule = new AdminPortalModule(
        $dataDir,
        $portalConfig,
        $tpl,
        $storage,
        $serverManager
    );
    $service->addModule($adminPortalModule);

    if (0 !== count($twoFactorMethods)) {
        $twoFactorEnrollModule = new TwoFactorEnrollModule($storage, $twoFactorMethods, $session, $tpl);
        $service->addModule($twoFactorEnrollModule);
    }

    if (false !== $portalConfig->getEnableApi()) {
        $apiConfig = $portalConfig->getApiConfig();

        // OAuth module
        $secretKey = SecretKey::fromEncodedString(
            FileIO::readFile(
                sprintf('%s/oauth.key', $dataDir)
            )
        );

        $oauthServer = new OAuthServer(
            $storage,
            $clientDb,
            new PublicSigner($secretKey->getPublicKey(), $secretKey)
        );
        $oauthServer->setAccessTokenExpiry($apiConfig->getTokenExpiry());
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
