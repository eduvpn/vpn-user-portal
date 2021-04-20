<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\Jwt\Keys\EdDSA\SecretKey;
use fkooman\SeCookie\Cookie;
use fkooman\SeCookie\CookieOptions;
use fkooman\SeCookie\Session;
use fkooman\SeCookie\SessionOptions;
use LC\Portal\CA\VpnCa;
use LC\Portal\Config;
use LC\Portal\Expiry;
use LC\Portal\FileIO;
use LC\Portal\Http\AccessHook;
use LC\Portal\Http\AdminHook;
use LC\Portal\Http\AdminPortalModule;
use LC\Portal\Http\Auth\ClientCertAuthModule;
use LC\Portal\Http\Auth\DbCredentialValidator;
use LC\Portal\Http\Auth\LdapCredentialValidator;
use LC\Portal\Http\Auth\MellonAuthModule;
use LC\Portal\Http\Auth\PhpSamlSpAuthModule;
use LC\Portal\Http\Auth\RadiusCredentialValidator;
use LC\Portal\Http\Auth\ShibAuthModule;
use LC\Portal\Http\Auth\UserPassAuthModule;
use LC\Portal\Http\CsrfProtectionHook;
use LC\Portal\Http\DisabledUserHook;
use LC\Portal\Http\HtmlResponse;
use LC\Portal\Http\InputValidation;
use LC\Portal\Http\LanguageSwitcherHook;
use LC\Portal\Http\LogoutModule;
use LC\Portal\Http\OAuthModule;
use LC\Portal\Http\PasswdModule;
use LC\Portal\Http\QrModule;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\Http\UpdateUserInfoHook;
use LC\Portal\Http\UserPassModule;
use LC\Portal\Http\VpnPortalModule;
use LC\Portal\LdapClient;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OAuth\PublicSigner;
use LC\Portal\OAuth\VpnOAuthServer;
use LC\Portal\OpenVpn\DaemonSocket;
use LC\Portal\OpenVpn\DaemonWrapper;
use LC\Portal\Random;
use LC\Portal\SeCookie;
use LC\Portal\SeSession;
use LC\Portal\Storage;
use LC\Portal\SysLogger;
use LC\Portal\TlsCrypt;
use LC\Portal\Tpl;

$logger = new SysLogger('vpn-user-portal');

try {
    $request = new Request($_SERVER, $_GET, $_POST);
    FileIO::createDir($baseDir.'/data', 0700);
    $config = Config::fromFile($baseDir.'/config/config.php');

    $templateDirs = [
        $baseDir.'/views',
        $baseDir.'/config/views',
    ];
    $localeDirs = [
        $baseDir.'/locale',
        $baseDir.'/config/locale',
    ];
    if (null !== $styleName = $config->optionalString('styleName')) {
        $templateDirs[] = $baseDir.'/views/'.$styleName;
        $templateDirs[] = $baseDir.'/config/views/'.$styleName;
        $localeDirs[] = $baseDir.'/locale/'.$styleName;
        $localeDirs[] = $baseDir.'/config/locale/'.$styleName;
    }

    $vpnCaPath = $config->requireString('vpnCaPath', '/usr/bin/vpn-ca');
    $ca = new VpnCa($baseDir.'/data/ca', 'EdDSA', $vpnCaPath);

    $sessionExpiry = Expiry::calculate(
        new DateInterval($config->requireString('sessionExpiry', 'P90D')),
        $ca->caExpiresAt()
    );

    $dateTime = new DateTimeImmutable();
    if ($dateTime->add(new DateInterval('PT30M')) >= $dateTime->add($sessionExpiry)) {
        throw new RuntimeException('sessionExpiry MUST be > PT30M');
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
            SessionOptions::init(),
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
    $authModuleCfg = $config->requireString('authModule', 'DbAuthModule');

    $tpl = new Tpl($templateDirs, $localeDirs, $baseDir.'/web');
    $tpl->setLanguage($uiLang);
    $templateDefaults = [
        'requestUri' => $request->getUri(),
        'requestRoot' => $request->getRoot(),
        'requestRootUri' => $request->getRootUri(),
        'supportedLanguages' => $supportedLanguages,
        'uiLang' => $uiLang,
        'portalVersion' => trim(FileIO::readFile($baseDir.'/VERSION')),
        'isAdmin' => false,
        'useRtl' => 0 === strpos($uiLang, 'ar_') || 0 === strpos($uiLang, 'fa_') || 0 === strpos($uiLang, 'he_'),
    ];

    $tpl->addDefault($templateDefaults);

    $storage = new Storage(
        new PDO(
            $config->s('Db')->requireString('dbDsn', 'sqlite://'.$baseDir.'/data/db.sqlite'),
            $config->s('Db')->optionalString('dbUser'),
            $config->s('Db')->optionalString('dbPass')
        ),
        $baseDir.'/schema'
    );
    $storage->update();

    $service = new Service();
    $service->addBeforeHook(new CsrfProtectionHook());
    $service->addBeforeHook(new LanguageSwitcherHook(array_keys($supportedLanguages), $seCookie));

    switch ($authModuleCfg) {
        case 'BasicAuthModule':
            $authModule = new LC\Portal\Http\Auth\BasicAuthModule(
                [
                    'foo' => 'bar',
                ]
            );
            break;
        case 'PhpSamlSpAuthModule':
            $authModule = new PhpSamlSpAuthModule($config->s('PhpSamlSpAuthModule'));
            break;
        case 'DbAuthModule':
            $authModule = new UserPassAuthModule($seSession, $tpl);
            $service->addModule(
                new UserPassModule(
                    new DbCredentialValidator($storage),
                    $seSession,
                    $tpl
                )
            );
            // when using local database, users are allowed to change their own
            // password
            $service->addModule(
                new PasswdModule(
                    new DbCredentialValidator($storage),
                    $tpl,
                    $storage
                )
            );
            break;
        case 'MellonAuthModule':
            $authModule = new MellonAuthModule($config->s('MellonAuthModule'));
            break;
        case 'ShibAuthModule':
            $authModule = new ShibAuthModule(
                $config->s('ShibAuthModule')->requireString('userIdAttribute'),
                $config->s('ShibAuthModule')->requireArray('permissionAttributeList', [])
            );
            break;
        case 'ClientCertAuthModule':
            $authModule = new ClientCertAuthModule();
            break;
        case 'RadiusAuthModule':
            $authModule = new UserPassAuthModule($seSession, $tpl);
            $service->addModule(
                new UserPassModule(
                    new RadiusCredentialValidator(
                        $logger,
                        $config->requireArray('serverList'),
                        $config->optionalString('addRealm'),
                        $config->optionalString('nasIdentifier')
                    ),
                    $seSession,
                    $tpl
                )
            );
            break;
        case 'LdapAuthModule':
            $ldapClient = new LdapClient(
                $config->s('LdapAuthModule')->requireString('ldapUri')
            );
            $authModule = new UserPassAuthModule($seSession, $tpl);
            $service->addModule(
                new UserPassModule(
                    new LdapCredentialValidator(
                        $logger,
                        $ldapClient,
                        $config->s('LdapAuthModule')->optionalString('bindDnTemplate'),
                        $config->s('LdapAuthModule')->optionalString('baseDn'),
                        $config->s('LdapAuthModule')->optionalString('userFilterTemplate'),
                        $config->s('LdapAuthModule')->optionalString('userIdAttribute'),
                        $config->s('LdapAuthModule')->optionalString('addRealm'),
                        $config->s('LdapAuthModule')->requireArray('permissionAttributeList', []),
                        $config->s('LdapAuthModule')->optionalString('searchBindDn'),
                        $config->s('LdapAuthModule')->optionalString('searchBindPass')
                    ),
                    $seSession,
                    $tpl
                )
            );
            break;
        default:
            throw new RuntimeException('unsupported authentication mechanism');
    }

    $service->setAuthModule($authModule);
    $tpl->addDefault(['authModule' => $authModuleCfg]);
    $tpl->addDefault(['_show_logout_button' => $authModule->supportsLogout()]);

    if (null !== $accessPermissionList = $config->optionalArray('accessPermissionList')) {
        // hasAccess
        $service->addBeforeHook(new AccessHook($accessPermissionList));
    }

    $service->addBeforeHook(new DisabledUserHook($storage));
    $service->addBeforeHook(new UpdateUserInfoHook($seSession, $storage));
    $service->addModule(new QrModule());

    // isAdmin
    $service->addBeforeHook(
        new AdminHook(
            $config->requireArray('adminPermissionList', []),
            $config->requireArray('adminUserIdList', []),
            $tpl
        )
    );

    $daemonWrapper = new DaemonWrapper(
        $config,
        $storage,
        new DaemonSocket($baseDir.'/config/vpn-daemon', $config->requireBool('vpnDaemonTls', true)),
        $logger
    );

    $oauthClientDb = new ClientDb();

    // portal module
    $vpnPortalModule = new VpnPortalModule(
        $config,
        $tpl,
        $seSession,
        $daemonWrapper,
        $storage,
        new TlsCrypt($baseDir.'/data'),
        new Random(),
        $ca,
        $oauthClientDb,
        $sessionExpiry
    );
    $service->addModule($vpnPortalModule);

    $adminPortalModule = new AdminPortalModule(
        $baseDir.'/data',
        $config,
        $tpl,
        $ca,
        $daemonWrapper,
        $storage
    );
    $service->addModule($adminPortalModule);

    // OAuth module
    $secretKey = SecretKey::fromEncodedString(FileIO::readFile($baseDir.'/config/oauth.key'));
    $oauthServer = new VpnOAuthServer(
        $storage,
        $oauthClientDb,
        new PublicSigner($secretKey->getPublicKey(), $secretKey)
    );
    $oauthServer->setAccessTokenExpiry(new DateInterval($config->s('Api')->requireString('tokenExpiry', 'PT1H')));
    $oauthServer->setRefreshTokenExpiry($sessionExpiry);

    $oauthModule = new OAuthModule(
        $tpl,
        $oauthServer
    );
    $service->addModule($oauthModule);
    $service->addModule(new LogoutModule($authModule, $seSession));

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new HtmlResponse($e->getMessage(), [], 500);
    $response->send();
}
