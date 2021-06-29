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

use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use fkooman\OAuth\Server\SimpleSigner;
use LC\Portal\CA\VpnCa;
use LC\Portal\Config;
use LC\Portal\Dt;
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
use LC\Portal\Http\LogoutModule;
use LC\Portal\Http\OAuthModule;
use LC\Portal\Http\PasswdModule;
use LC\Portal\Http\PhpCookie;
use LC\Portal\Http\QrModule;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\Http\UpdateUserInfoHook;
use LC\Portal\Http\UserPassModule;
use LC\Portal\Http\VpnPortalModule;
use LC\Portal\HttpClient\CurlHttpClient;
use LC\Portal\LdapClient;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OAuth\VpnOAuthServer;
use LC\Portal\OpenVpn\DaemonSocket;
use LC\Portal\OpenVpn\DaemonWrapper;
use LC\Portal\PhpSession;
use LC\Portal\Random;
use LC\Portal\ServerInfo;
use LC\Portal\Storage;
use LC\Portal\SysLogger;
use LC\Portal\TlsCrypt;
use LC\Portal\Tpl;
use LC\Portal\WgServerConfig;
use LC\Portal\WireGuard\Wg;
use LC\Portal\WireGuard\WgDaemon;

$logger = new SysLogger('vpn-user-portal');

try {
    $request = Request::createFromGlobals();
    FileIO::createDir($baseDir.'/data', 0700);
    $config = Config::fromFile($baseDir.'/config/config.php');

    $templateDirs = [
        $baseDir.'/views',
        $baseDir.'/config/views',
    ];
    $translationDirs = [
        $baseDir.'/locale',
        $baseDir.'/config/locale',
    ];
    if (null !== $styleName = $config->styleName()) {
        $templateDirs[] = $baseDir.'/views/'.$styleName;
        $templateDirs[] = $baseDir.'/config/views/'.$styleName;
        $translationDirs[] = $baseDir.'/locale/'.$styleName;
        $translationDirs[] = $baseDir.'/config/locale/'.$styleName;
    }

    $ca = new VpnCa($baseDir.'/data/ca', 'EdDSA', $config->vpnCaPath(), $config->caExpiry());

    $sessionExpiry = Expiry::calculate(
        $config->sessionExpiry(),
        $ca->caCert()->validTo()
    );

    $dateTime = Dt::get();
    if ($dateTime->add(new DateInterval('PT30M')) >= $dateTime->add($sessionExpiry)) {
        throw new RuntimeException('sessionExpiry MUST be > PT30M');
    }

    $db = new PDO(
        $config->s('Db')->requireString('dbDsn', 'sqlite://'.$baseDir.'/data/db.sqlite'),
        $config->s('Db')->optionalString('dbUser'),
        $config->s('Db')->optionalString('dbPass')
    );
    $storage = new Storage($db, $baseDir.'/schema');
    $storage->update();

    $cookieBackend = new PhpCookie($config->secureCookie(), $request->getRoot());
    $sessionBackend = new PhpSession($config->secureCookie(), $request->getRoot());

    // determine whether or not we want to use another language for the UI
    if (null === $uiLanguage = $request->getCookie('L')) {
        $uiLanguage = $config->defaultLanguage();
    }
    $tpl = new Tpl($templateDirs, $translationDirs, $baseDir.'/web', $uiLanguage);

    // Authentication
    $authModuleCfg = $config->authModule();

    $templateDefaults = [
        'enableConfigDownload' => $config->enableConfigDownload(),
        'requestUri' => $request->getUri(),
        'requestRoot' => $request->getRoot(),
        'requestRootUri' => $request->getRootUri(),
        'enabledLanguages' => $config->requireArray('enabledLanguages', ['en-US']),
        'portalVersion' => trim(FileIO::readFile($baseDir.'/VERSION')),
        'isAdmin' => false,
        'uiLanguage' => $uiLanguage,
        '_show_logout_button' => true,
    ];

    $tpl->addDefault($templateDefaults);

    $service = new Service();
    $service->addBeforeHook(new CsrfProtectionHook());

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
            $authModule = new UserPassAuthModule($sessionBackend, $tpl);
            $service->addModule(
                new UserPassModule(
                    new DbCredentialValidator($storage),
                    $sessionBackend,
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
            $authModule = new UserPassAuthModule($sessionBackend, $tpl);
            $service->addModule(
                new UserPassModule(
                    new RadiusCredentialValidator(
                        $logger,
                        $config->s('RadiusAuthModule')->requireArray('serverList'),
                        $config->s('RadiusAuthModule')->optionalString('addRealm'),
                        $config->s('RadiusAuthModule')->optionalString('nasIdentifier')
                    ),
                    $sessionBackend,
                    $tpl
                )
            );
            break;
        case 'LdapAuthModule':
            $ldapClient = new LdapClient(
                $config->s('LdapAuthModule')->requireString('ldapUri')
            );
            $authModule = new UserPassAuthModule($sessionBackend, $tpl);
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
                    $sessionBackend,
                    $tpl
                )
            );
            break;
        default:
            throw new RuntimeException('unsupported authentication mechanism');
    }

    $service->setAuthModule($authModule);
    $tpl->addDefault(['authModule' => $authModuleCfg]);

    if (null !== $accessPermissionList = $config->optionalArray('accessPermissionList')) {
        // hasAccess
        $service->addBeforeHook(new AccessHook($accessPermissionList));
    }

    $service->addBeforeHook(new DisabledUserHook($storage));
    $service->addBeforeHook(new UpdateUserInfoHook($sessionBackend, $storage, $authModule));
    $service->addModule(new QrModule());

    // isAdmin
    $adminHook = new AdminHook(
        $config->requireArray('adminPermissionList', []),
        $config->requireArray('adminUserIdList', []),
        $tpl
    );

    $service->addBeforeHook($adminHook);

    $daemonWrapper = new DaemonWrapper(
        $config,
        $storage,
        new DaemonSocket($baseDir.'/config/vpn-daemon', $config->vpnDaemonTls()),
        $logger
    );

    $oauthClientDb = new ClientDb();
    $oauthStorage = new OAuthStorage($db, 'oauth_');

    // portal module
    $vpnPortalModule = new VpnPortalModule(
        $config,
        $tpl,
        $cookieBackend,
        $sessionBackend,
        $daemonWrapper,
        new Wg(new WgDaemon(new CurlHttpClient()), $storage),
        $storage,
        $oauthStorage,
        new TlsCrypt($baseDir.'/data'),
        new Random(),
        $ca,
        $oauthClientDb,
        $sessionExpiry
    );
    $service->addModule($vpnPortalModule);

    $oauthSigner = new SimpleSigner(FileIO::readFile($baseDir.'/config/oauth.simple.key'));
    $wgServerConfig = new WgServerConfig($baseDir.'/data');
    $adminPortalModule = new AdminPortalModule(
        $baseDir.'/data',
        $config,
        $tpl,
        $daemonWrapper,
        $storage,
        $oauthStorage,
        $adminHook,
        new ServerInfo(
            $ca,
            $wgServerConfig->publicKey()
        )
    );
    $service->addModule($adminPortalModule);

    // OAuth module
    $oauthServer = new VpnOAuthServer(
        $oauthStorage,
        $oauthClientDb,
        $oauthSigner
    );
    $oauthServer->setAccessTokenExpiry($config->apiConfig()->tokenExpiry());
    $oauthServer->setRefreshTokenExpiry($sessionExpiry);

    $oauthModule = new OAuthModule(
        $tpl,
        $oauthServer
    );
    $service->addModule($oauthModule);
    $service->addModule(new LogoutModule($authModule, $sessionBackend));

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new HtmlResponse($e->getMessage().$e->getTraceAsString(), [], 500);
    $response->send();
}
