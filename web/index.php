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
use fkooman\SeCookie\Cookie;
use fkooman\SeCookie\CookieOptions;
use fkooman\SeCookie\Session;
use fkooman\SeCookie\SessionOptions;
use LC\Common\Config;
use LC\Common\FileIO;
use LC\Common\Http\CsrfProtectionHook;
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\InputValidation;
use LC\Common\Http\LanguageSwitcherHook;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\StaticPermissions;
use LC\Common\Logger;
use LC\Common\Random;
use LC\Portal\AccessHook;
use LC\Portal\AdminHook;
use LC\Portal\AdminPortalModule;
use LC\Portal\CA\VpnCa;
use LC\Portal\ClientCertAuthentication;
use LC\Portal\ClientFetcher;
use LC\Portal\DisabledUserHook;
use LC\Portal\FormLdapAuthentication;
use LC\Portal\FormPdoAuthentication;
use LC\Portal\FormRadiusAuthentication;
use LC\Portal\LogoutModule;
use LC\Portal\MellonAuthentication;
use LC\Portal\OAuth\PublicSigner;
use LC\Portal\OAuthModule;
use LC\Portal\OpenVpn\DaemonSocket;
use LC\Portal\OpenVpn\DaemonWrapper;
use LC\Portal\PhpSamlSpAuthentication;
use LC\Portal\QrModule;
use LC\Portal\SeCookie;
use LC\Portal\SeSession;
use LC\Portal\ShibAuthentication;
use LC\Portal\Storage;
use LC\Portal\TlsCrypt;
use LC\Portal\Tpl;
use LC\Portal\TwoFactorEnrollModule;
use LC\Portal\TwoFactorHook;
use LC\Portal\TwoFactorModule;
use LC\Portal\UpdateSessionInfoHook;
use LC\Portal\VpnPortalModule;

$logger = new Logger('vpn-user-portal');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir, 0700);
    $configDir = sprintf('%s/config', $baseDir);

    $config = Config::fromFile(sprintf('%s/config.php', $configDir));
    $templateDirs = [
        sprintf('%s/views', $baseDir),
        sprintf('%s/config/views', $baseDir),
    ];
    $localeDirs = [
        sprintf('%s/locale', $baseDir),
        sprintf('%s/config/locale', $baseDir),
    ];
    if (null !== $styleName = $config->optionalString('styleName')) {
        $templateDirs[] = sprintf('%s/views/%s', $baseDir, $styleName);
        $templateDirs[] = sprintf('%s/config/views/%s', $baseDir, $styleName);
        $localeDirs[] = sprintf('%s/locale/%s', $baseDir, $styleName);
        $localeDirs[] = sprintf('%s/config/locale/%s', $baseDir, $styleName);
    }

    $sessionExpiry = $config->requireString('sessionExpiry', 'P90D');

    // we always want browser session to expiry after PT8H hours, *EXCEPT* when
    // the configured "sessionExpiry" is < PT8H, then we want to follow that
    // setting...
    $sessionOptions = SessionOptions::init();
    $dateTime = new DateTime();
    if (date_add(clone $dateTime, new DateInterval('PT30M')) > date_add(clone $dateTime, new DateInterval($sessionExpiry))) {
        $sessionOptions = SessionOptions::init()->withExpiresIn(new DateInterval($sessionExpiry));
    }

    $secureCookie = $config->requireBool('secureCookie', true);
    $cookieOptions = $secureCookie ? CookieOptions::init() : CookieOptions::init()->withoutSecure();
    $seCookie = new SeCookie(
        new Cookie(
            $cookieOptions
                ->withSameSiteLax()
                ->withMaxAge(60 * 60 * 24 * 90)  // 90 days
        )
    );
    $seSession = new SeSession(
        new Session(
            $sessionOptions,
            $cookieOptions
                ->withPath($request->getRoot())
                ->withSameSiteLax()
        )
    );

    $supportedLanguages = $config->requireArray('supportedLanguages', ['en_US' => 'English']);
    // the first listed language is the default language
    $uiLang = array_keys($supportedLanguages)[0];
    if (null !== $cookieUiLang = $seCookie->get('ui_lang')) {
        $uiLang = InputValidation::uiLang($cookieUiLang);
    }

    // Authentication
    $authMethod = $config->requireString('authMethod', 'FormPdoAuthentication');

    $tpl = new Tpl($templateDirs, $localeDirs, sprintf('%s/web', $baseDir));
    $tpl->setLanguage($uiLang);
    $templateDefaults = [
        'requestUri' => $request->getUri(),
        'requestRoot' => $request->getRoot(),
        'requestRootUri' => $request->getRootUri(),
        'supportedLanguages' => $supportedLanguages,
        '_show_logout_button' => true,
        'uiLang' => $uiLang,
        'portalVersion' => trim(FileIO::readFile(sprintf('%s/VERSION', $baseDir))),
        'isAdmin' => false,
        'useRtl' => 0 === strpos($uiLang, 'ar_') || 0 === strpos($uiLang, 'fa_') || 0 === strpos($uiLang, 'he_'),
    ];
    if ('ClientCertAuthentication' === $authMethod) {
        $templateDefaults['_show_logout_button'] = false;
    }
    $tpl->addDefault($templateDefaults);

    $service = new Service($tpl);
    $service->addBeforeHook('csrf_protection', new CsrfProtectionHook());
    $service->addBeforeHook('language_switcher', new LanguageSwitcherHook(array_keys($supportedLanguages), $seCookie));

    $logoutUrl = null;
    $returnParameter = 'ReturnTo';
    if ('PhpSamlSpAuthentication' === $authMethod) {
        $logoutUrl = $request->getScheme().'://'.$request->getAuthority().'/php-saml-sp/logout';
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

    $service->addModule(new LogoutModule($seSession, $logoutUrl, $returnParameter));
    switch ($authMethod) {
        case 'PhpSamlSpAuthentication':
            $phpSamlSpAuthentication = new PhpSamlSpAuthentication(
                $config->s('PhpSamlSpAuthentication')
            );
            $service->addBeforeHook('auth', $phpSamlSpAuthentication);
            break;
        case 'MellonAuthentication':
            $service->addBeforeHook('auth', new MellonAuthentication($config->s('MellonAuthentication')));
            break;
        case 'ClientCertAuthentication':
            $service->addBeforeHook('auth', new ClientCertAuthentication());
            break;
        case 'ShibAuthentication':
            $service->addBeforeHook('auth', new ShibAuthentication($config->s('ShibAuthentication')));
            break;
        case 'FormLdapAuthentication':
            $formLdapAuthentication = new FormLdapAuthentication(
                $config->s('FormLdapAuthentication'),
                $seSession,
                $tpl,
                $logger
            );
            if (null !== $staticPermissions) {
                $formLdapAuthentication->setStaticPermissions($staticPermissions);
            }
            $service->addBeforeHook('auth', $formLdapAuthentication);
            $service->addModule($formLdapAuthentication);
            break;
        case 'FormRadiusAuthentication':
            $formRadiusAuthentication = new FormRadiusAuthentication(
                $config->s('FormRadiusAuthentication'),
                $seSession,
                $tpl,
                $logger
            );
            if (null !== $staticPermissions) {
                $formRadiusAuthentication->setStaticPermissions($staticPermissions);
            }
            $service->addBeforeHook('auth', $formRadiusAuthentication);
            $service->addModule($formRadiusAuthentication);
            break;
        case 'FormPdoAuthentication':
            $formPdoAuthentication = new FormPdoAuthentication(
                $seSession,
                $tpl,
                $storage
            );
            if (null !== $staticPermissions) {
                $formPdoAuthentication->setStaticPermissions($staticPermissions);
            }
            $service->addBeforeHook('auth', $formPdoAuthentication);
            $service->addModule($formPdoAuthentication);
            break;
         default:
            throw new RuntimeException('unsupported authentication mechanism');
    }

    $tpl->addDefault(
        [
            'allowPasswordChange' => 'FormPdoAuthentication' === $authMethod,
        ]
    );

    if (null !== $accessPermissionList = $config->optionalArray('accessPermissionList')) {
        // hasAccess
        $service->addBeforeHook(
            'has_access',
            new AccessHook(
                $accessPermissionList
            )
        );
    }

    if ($config->requireBool('enableTwoFactor', false)) {
        $service->addBeforeHook(
            'two_factor',
            new TwoFactorHook(
                $storage,
                $seSession,
                $tpl,
                $config->requireBool('requireTwoFactor', false)
            )
        );
    }

    $service->addBeforeHook('disabled_user', new DisabledUserHook($storage));
    $service->addBeforeHook('update_session_info', new UpdateSessionInfoHook($seSession, $storage, new DateInterval($sessionExpiry)));

    $service->addModule(new QrModule());

    // two factor module
    if ($config->requireBool('enableTwoFactor', false)) {
        $twoFactorModule = new TwoFactorModule($storage, $seSession, $tpl);
        $service->addModule($twoFactorModule);
    }

    // isAdmin
    $service->addBeforeHook(
        'is_admin',
        new AdminHook(
            $config->requireArray('adminPermissionList', []),
            $config->requireArray('adminUserIdList', []),
            $tpl
        )
    );

    $clientFetcher = new ClientFetcher($config);

    $vpnCaDir = sprintf('%s/ca', $dataDir);
    $vpnCaPath = $config->requireString('vpnCaPath', '/usr/bin/vpn-ca');
    $ca = new VpnCa($vpnCaDir, 'EdDSA', $vpnCaPath);

    $daemonWrapper = new DaemonWrapper(
        $config,
        $storage,
        new DaemonSocket(sprintf('%s/vpn-daemon', $configDir), $config->requireBool('vpnDaemonTls', true)),
        $logger
    );

    // portal module
    $vpnPortalModule = new VpnPortalModule(
        $config,
        $tpl,
        $seSession,
        $daemonWrapper,
        $storage,
        new TlsCrypt($dataDir),
        new Random(),
        $ca,
        $clientFetcher
    );
    $service->addModule($vpnPortalModule);

    $adminPortalModule = new AdminPortalModule(
        $dataDir,
        $config,
        $tpl,
        $ca,
        $daemonWrapper,
        $storage
    );
    $service->addModule($adminPortalModule);

    if ($config->requireBool('enableTwoFactor', false)) {
        $twoFactorEnrollModule = new TwoFactorEnrollModule($seSession, $tpl, $storage);
        $service->addModule($twoFactorEnrollModule);
    }

    // OAuth module
    $secretKey = SecretKey::fromEncodedString(
        FileIO::readFile(
            sprintf('%s/config/oauth.key', $baseDir)
        )
    );
    $oauthServer = new OAuthServer(
        $storage,
        $clientFetcher,
        new PublicSigner($secretKey->getPublicKey(), $secretKey)
    );
    $oauthServer->setAccessTokenExpiry(new DateInterval($config->s('Api')->requireString('tokenExpiry', 'PT1H')));
    $oauthModule = new OAuthModule(
        $tpl,
        $oauthServer
    );
    $service->addModule($oauthModule);
    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new HtmlResponse($e->getMessage(), 500);
    $response->send();
}
