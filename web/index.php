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
use LC\Portal\Config\PortalConfig;
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
use LC\Portal\Http\OAuthModule;
use LC\Portal\Http\PasswdModule;
use LC\Portal\Http\RadiusAuth;
use LC\Portal\Http\Request;
use LC\Portal\Http\SamlAuthenticationHook;
use LC\Portal\Http\SamlModule;
use LC\Portal\Http\Service;
use LC\Portal\Http\TwoFactorEnrollModule;
use LC\Portal\Http\TwoFactorHook;
use LC\Portal\Http\TwoFactorModule;
use LC\Portal\Http\UpdateSessionInfoHook;
use LC\Portal\Http\VpnPortalModule;
use LC\Portal\LdapClient;
use LC\Portal\Logger;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OAuth\PublicSigner;
use LC\Portal\OpenVpn\ServerManager;
use LC\Portal\OpenVpn\TlsCrypt;
use LC\Portal\Storage;
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
    $configDir = sprintf('%s/config', $baseDir);

    FileIO::createDir($dataDir, 0700);

    $portalConfig = PortalConfig::fromFile(sprintf('%s/config.php', $configDir));

    $templateDirs = [
        sprintf('%s/views', $baseDir),
        sprintf('%s/views', $configDir),
    ];

    $styleConfig = null;
    if (null !== $styleName = $portalConfig->getStyleName()) {
        $templateDirs[] = sprintf('%s/views/%s', $baseDir, $styleName);
        //        $styleConfig = Config::fromFile(sprintf('%s/%s.php', $configDir, $styleName));
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
    $supportedLanguages = $portalConfig->getSupportedLanguages();
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
    $storage->update();

    $service->addModule(new LogoutModule($session, $logoutUrl, $returnParameter));
    switch ($authMethod) {
        case 'SamlAuthentication':
            $samlAuthenticationConfig = $portalConfig->getSamlAuthenticationConfig();
            if (null === $spEntityId = $samlAuthenticationConfig->getSpEntityId()) {
                $spEntityId = $request->getRootUri().'_saml/metadata';
            }
            $userIdAttribute = $samlAuthenticationConfig->getUserIdAttribute();
            $permissionAttributeList = $samlAuthenticationConfig->getPermissionAttributeList();
            $spInfo = new SpInfo(
                $spEntityId,
                PrivateKey::fromFile(sprintf('%s/saml.key', $configDir)),
                PublicKey::fromFile(sprintf('%s/saml.crt', $configDir)),
                $request->getRootUri().'_saml/acs'
            );
            $spInfo->setSloUrl($request->getRootUri().'_saml/slo');
            $samlSp = new SP(
                $spInfo,
                new XmlIdpInfoSource($samlAuthenticationConfig->requireString('idpMetadata'))
            );
            $service->addBeforeHook(
                'auth',
                new SamlAuthenticationHook(
                    $samlSp,
                    $samlAuthenticationConfig->getIdpEntityId(),
                    $userIdAttribute,
                    $permissionAttributeList,
                    $samlAuthenticationConfig->getAuthnContext(),
                    $samlAuthenticationConfig->getPermissionAuthnContext(),
                    $samlAuthenticationConfig->getPermissionSessionExpiry()
                )
            );
            $service->addModule(
                new SamlModule(
                    $samlSp,
                    $samlAuthenticationConfig->getDiscoUrl()
                )
            );

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
    $tlsCrypt = TlsCrypt::fromFile(sprintf('%s/tls-crypt.key', $configDir));
    $serverManager = new ServerManager($portalConfig->getProfileConfigList(), $logger, new ManagementSocket());
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
    $graph = new Graph();
    $graph->setFontList($fontList);
    if (null !== $styleConfig) {
        $graph->setBarColor($styleConfig->getItem('barColor'));
    }

    $adminPortalModule = new AdminPortalModule(
        $dataDir,
        $portalConfig->getProfileConfigList(),
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

    if (false !== $portalConfig->getEnableApi()) {
        $apiConfig = $portalConfig->getApiConfig();

        // OAuth module
        $secretKey = SecretKey::fromEncodedString(
            FileIO::readFile(
                sprintf('%s/oauth.key', $configDir)
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
