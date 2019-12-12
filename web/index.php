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
use LC\Common\Config;
use LC\Common\FileIO;
use LC\Common\Http\CsrfProtectionHook;
use LC\Common\Http\FormAuthenticationHook;
use LC\Common\Http\FormAuthenticationModule;
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\InputValidation;
use LC\Common\Http\LanguageSwitcherHook;
use LC\Common\Http\LdapAuth;
use LC\Common\Http\RadiusAuth;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\StaticPermissions;
use LC\Common\Http\TwoFactorHook;
use LC\Common\Http\TwoFactorModule;
use LC\Common\HttpClient\CurlHttpClient;
use LC\Common\HttpClient\ServerClient;
use LC\Common\LdapClient;
use LC\Common\Logger;
use LC\Common\Tpl;
use LC\Portal\AccessHook;
use LC\Portal\AdminHook;
use LC\Portal\AdminPortalModule;
use LC\Portal\ClientFetcher;
use LC\Portal\DisabledUserHook;
use LC\Portal\LogoutModule;
use LC\Portal\MellonAuthenticationHook;
use LC\Portal\OAuth\PublicSigner;
use LC\Portal\OAuthModule;
use LC\Portal\PasswdModule;
use LC\Portal\SamlAuthenticationHook;
use LC\Portal\SamlModule;
use LC\Portal\ShibAuthenticationHook;
use LC\Portal\OpenidcAuthenticationHook;
use LC\Portal\Storage;
use LC\Portal\TwoFactorEnrollModule;
use LC\Portal\UpdateSessionInfoHook;
use LC\Portal\VpnPortalModule;

$logger = new Logger('vpn-user-portal');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir, 0700);

    $config = Config::fromFile(sprintf('%s/config/config.php', $baseDir));

    $templateDirs = [
        sprintf('%s/views', $baseDir),
        sprintf('%s/config/views', $baseDir),
    ];

    if ($config->hasItem('styleName')) {
        $styleName = $config->getItem('styleName');
        $templateDirs[] = sprintf('%s/views/%s', $baseDir, $styleName);
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

    $supportedLanguages = $config->getSection('supportedLanguages')->toArray();
    // the first listed language is the default language
    $uiLang = array_keys($supportedLanguages)[0];
    if (array_key_exists('ui_lang', $_COOKIE)) {
        $uiLang = InputValidation::uiLang($_COOKIE['ui_lang']);
    }
    $languageFileList = [];
    if ('en_US' !== $uiLang) {
        if (array_key_exists($uiLang, $supportedLanguages)) {
            $languageFileList[] = sprintf('%s/locale/%s.php', $baseDir, $uiLang);
        }
        // check whether the theme also installed a language file, and add this
        // as well then...
        if ($config->hasItem('styleName')) {
            $styleName = $config->getItem('styleName');
            if (FileIO::exists(sprintf('%s/locale/%s/%s.php', $baseDir, $styleName, $uiLang))) {
                $languageFileList[] = sprintf('%s/locale/%s/%s.php', $baseDir, $styleName, $uiLang);
            }
        }
    }

    $tpl = new Tpl($templateDirs, $languageFileList);
    $tpl->addDefault(
        [
            'requestUri' => $request->getUri(),
            'requestRoot' => $request->getRoot(),
            'requestRootUri' => $request->getRootUri(),
            'supportedLanguages' => $supportedLanguages,
            'uiLang' => $uiLang,
            'useRtl' => 0 === strpos($uiLang, 'ar_') || 0 === strpos($uiLang, 'fa_') || 0 === strpos($uiLang, 'he_'),
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
    $returnParameter = 'ReturnTo';
    if ('SamlAuthentication' === $authMethod) {
        $logoutUrl = $request->getRootUri().'_saml/logout';
    }
    if ('MellonAuthentication' === $authMethod) {
        $logoutUrl = $request->getScheme().'://'.$request->getAuthority().'/saml/logout';
    }
    if ('ShibAuthentication' === $authMethod) {
        $logoutUrl = $request->getScheme().'://'.$request->getAuthority().'/Shibboleth.sso/Logout';
        $returnParameter = 'return';
    }

    // StaticPermissions for the local authentiction mechanisms
    // (PDO, LDAP, RADIUS)
    $staticPermissions = null;
    $staticPermissionsFile = sprintf('%s/config/static_permissions.json', $baseDir);
    if (FileIO::exists($staticPermissionsFile)) {
        $staticPermissions = new StaticPermissions($staticPermissionsFile);
    }

    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir),
        new DateInterval($sessionExpiry)
    );
    $storage->update();

    $service->addModule(new LogoutModule($session, $logoutUrl, $returnParameter));
    switch ($authMethod) {
        case 'SamlAuthentication':
            $spEntityId = $config->getSection('SamlAuthentication')->optionalItem('spEntityId', $request->getRootUri().'_saml/metadata');
            $serviceName = $config->getSection('SamlAuthentication')->optionalItem('serviceName', []);

            $userIdAttribute = $config->getSection('SamlAuthentication')->getItem('userIdAttribute');

            /** @var array<string>|string|null */
            $permissionAttribute = $config->getSection('SamlAuthentication')->optionalItem('permissionAttribute');
            if (is_string($permissionAttribute)) {
                $permissionAttribute = [$permissionAttribute];
            }
            if (null === $permissionAttribute) {
                $permissionAttribute = [];
            }

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
                    $config->getSection('MellonAuthentication')->optionalItem('permissionAttribute'),
                    $config->getSection('MellonAuthentication')->optionalItem('nameIdSerialization', false),
                    $config->getSection('MellonAuthentication')->optionalItem('spEntityId')
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
        case 'OpenidcAuthentication':
            $service->addBeforeHook(
                'auth',
                new OpenidcAuthenticationHook(
                    $config->getSection('OpenidcAuthentication')->getItem('subjectClaim'),
                    $config->getSection('OpenidcAuthentication')->optionalItem('permissionClaim')
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

            $fam = new FormAuthenticationModule(
                $userAuth,
                $session,
                $tpl
            );
            if (null !== $staticPermissions) {
                $fam->setStaticPermissions($staticPermissions);
            }
            $service->addModule($fam);

            break;
        case 'FormPdoAuthentication':
            $service->addBeforeHook(
                'auth',
                new FormAuthenticationHook(
                    $session,
                    $tpl
                )
            );

            $fam = new FormAuthenticationModule(
                $storage,
                $session,
                $tpl
            );
            if (null !== $staticPermissions) {
                $fam->setStaticPermissions($staticPermissions);
            }
            $service->addModule($fam);

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

            $fam = new FormAuthenticationModule(
                $userAuth,
                $session,
                $tpl
            );
            if (null !== $staticPermissions) {
                $fam->setStaticPermissions($staticPermissions);
            }
            $service->addModule($fam);

            break;
        default:
            throw new RuntimeException('unsupported authentication mechanism');
    }

    $tpl->addDefault(
        [
            'authMethod' => $authMethod,
        ]
    );

    if (null !== $accessPermissionList = $config->optionalItem('accessPermissionList')) {
        // hasAccess
        $service->addBeforeHook(
            'has_access',
            new AccessHook(
                $accessPermissionList
            )
        );
    }

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
    $service->addBeforeHook('update_session_info', new UpdateSessionInfoHook($session, $serverClient, new DateInterval($sessionExpiry)));

    // two factor module
    if (0 !== count($twoFactorMethods)) {
        $twoFactorModule = new TwoFactorModule($serverClient, $session, $tpl);
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

    $clientFetcher = new ClientFetcher($config);

    // portal module
    $vpnPortalModule = new VpnPortalModule(
        $config,
        $tpl,
        $serverClient,
        $session,
        $storage,
        $clientFetcher
    );
    $service->addModule($vpnPortalModule);

    $adminPortalModule = new AdminPortalModule(
        $tpl,
        $storage,
        $serverClient
    );
    $service->addModule($adminPortalModule);

    if (0 !== count($twoFactorMethods)) {
        $twoFactorEnrollModule = new TwoFactorEnrollModule($twoFactorMethods, $session, $tpl, $serverClient);
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
