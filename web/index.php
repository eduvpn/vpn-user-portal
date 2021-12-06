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
use fkooman\OAuth\Server\Signer;
use fkooman\SeCookie\Cookie;
use fkooman\SeCookie\CookieOptions;
use LC\Portal\Config;
use LC\Portal\ConnectionManager;
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
use LC\Portal\Http\Request;
use LC\Portal\Http\SeCookie;
use LC\Portal\Http\Service;
use LC\Portal\Http\SeSession;
use LC\Portal\Http\UpdateUserInfoHook;
use LC\Portal\Http\UserPassModule;
use LC\Portal\Http\VpnPortalModule;
use LC\Portal\HttpClient\CurlHttpClient;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OAuth\VpnOAuthServer;
use LC\Portal\OpenVpn\CA\VpnCa;
use LC\Portal\OpenVpn\TlsCrypt;
use LC\Portal\ServerInfo;
use LC\Portal\Storage;
use LC\Portal\SysLogger;
use LC\Portal\Tpl;
use LC\Portal\VpnDaemon;
use LC\Portal\WireGuard\ServerConfig as WireGuardServerConfig;

$logger = new SysLogger('vpn-user-portal');
$tpl = null;

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $request = Request::createFromGlobals();

    // determine perferred UI language
    if (null === $uiLanguage = $request->getCookie('L')) {
        $uiLanguage = $config->defaultLanguage();
    }
    $tpl = new Tpl(
        $baseDir,
        $config->styleName(),
        $uiLanguage,
        [
            'portalHostname' => gethostname(),
            'enableConfigDownload' => $config->enableConfigDownload(),
            'requestUri' => $request->getUri(),
            'requestRoot' => $request->getRoot(),
            'requestRootUri' => $request->getRootUri(),
            // XXX proper config option
            'enabledLanguages' => $config->requireStringArray('enabledLanguages', ['en-US']),
            'portalVersion' => trim(FileIO::readFile($baseDir.'/VERSION')),
            'isAdmin' => false,
            'uiLanguage' => $uiLanguage,
            '_show_logout_button' => true,
            'authModule' => $config->authModule(),
        ]
    );

    FileIO::createDir($baseDir.'/data', 0700);
    $ca = new VpnCa($baseDir.'/data/ca', $config->vpnCaPath());
    $sessionExpiry = Expiry::calculate(
        $config->sessionExpiry(),
        $ca->caCert()->validTo()
    );

    $dateTime = Dt::get();
    if ($dateTime->add(new DateInterval('PT30M')) >= $dateTime->add($sessionExpiry)) {
        throw new RuntimeException('sessionExpiry MUST be > PT30M');
    }

    $db = new PDO(
        $config->dbConfig($baseDir)->dbDsn(),
        $config->dbConfig($baseDir)->dbUser(),
        $config->dbConfig($baseDir)->dbPass()
    );
    $storage = new Storage($db, $baseDir.'/schema');
    $storage->update();

    $cookieOptions = CookieOptions::init()->withPath($request->getRoot());
    if (!$config->secureCookie()) {
        $cookieOptions = $cookieOptions->withoutSecure();
    }
    $cookieBackend = new SeCookie(new Cookie($cookieOptions->withMaxAge(60 * 60 * 24 * 90)->withSameSiteLax()));
    $sessionBackend = new SeSession($cookieOptions->withSameSiteStrict(), $config->sessionConfig());

    $service = new Service();
    $service->addBeforeHook(new CsrfProtectionHook());

    switch ($config->authModule()) {
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

        case 'ClientCertAuthModule':
            $authModule = new ClientCertAuthModule();

            break;

        case 'LdapAuthModule':
            $authModule = new UserPassAuthModule($sessionBackend, $tpl);
            $service->addModule(
                new UserPassModule(
                    new LdapCredentialValidator(
                        $config->ldapAuthConfig(),
                        $logger
                    ),
                    $sessionBackend,
                    $tpl
                )
            );

            break;

        case 'RadiusAuthModule':
            $authModule = new UserPassAuthModule($sessionBackend, $tpl);
            $service->addModule(
                new UserPassModule(
                    new RadiusCredentialValidator(
                        $logger,
                        $config->radiusAuthConfig()
                    ),
                    $sessionBackend,
                    $tpl
                )
            );

            break;

        case 'ShibAuthModule':
            $authModule = new ShibAuthModule(
                $config->s('ShibAuthModule')->requireString('userIdAttribute'),
                $config->s('ShibAuthModule')->requireStringArray('permissionAttributeList', [])
            );

            break;

        case 'MellonAuthModule':
            $authModule = new MellonAuthModule($config->mellonAuthConfig());

            break;

        case 'PhpSamlSpAuthModule':
            $authModule = new PhpSamlSpAuthModule($config->s('PhpSamlSpAuthModule'));

            break;

        default:
            throw new RuntimeException('unsupported authentication mechanism');
    }

    $service->setAuthModule($authModule);

    if (null !== $accessPermissionList = $config->optionalStringArray('accessPermissionList')) {
        // hasAccess
        $service->addBeforeHook(new AccessHook($accessPermissionList));
    }

    $service->addBeforeHook(new DisabledUserHook($storage));
    $service->addBeforeHook(new UpdateUserInfoHook($sessionBackend, $storage, $authModule));

    // isAdmin
    $adminHook = new AdminHook(
        $config->requireStringArray('adminPermissionList', []),
        $config->requireStringArray('adminUserIdList', []),
        $tpl
    );

    $service->addBeforeHook($adminHook);
    $oauthClientDb = new ClientDb();
    $oauthStorage = new OAuthStorage($db, 'oauth_');
    $wireGuardServerConfig = new WireGuardServerConfig(FileIO::readFile($baseDir.'/config/wireguard.secret.key'), $config->wgPort());
    $oauthKey = FileIO::readFile($baseDir.'/config/oauth.key');
    $oauthSigner = new Signer($oauthKey);
    $tlsCrypt = new TlsCrypt($baseDir.'/data');
    $serverInfo = new ServerInfo(
        $ca,
        $tlsCrypt,
        FileIO::readFile($baseDir.'/config/wireguard.public.key'),
        $config->wgPort(),
        Signer::publicKeyFromSecretKey($oauthKey)
    );

    $vpnDaemon = new VpnDaemon(new CurlHttpClient($baseDir.'/config/vpn-daemon'), $logger);
    $connectionManager = new ConnectionManager($config, $vpnDaemon, $storage);

    // portal module
    $vpnPortalModule = new VpnPortalModule(
        $config,
        $tpl,
        $cookieBackend,
        $connectionManager,
        $storage,
        $oauthStorage,
        $serverInfo,
        $sessionExpiry
    );
    $service->addModule($vpnPortalModule);

    $adminPortalModule = new AdminPortalModule(
        $config,
        $tpl,
        $vpnDaemon,
        $connectionManager,
        $storage,
        $oauthStorage,
        $adminHook,
        $serverInfo
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
        $storage,
        $oauthServer,
        $tpl,
        $config->apiConfig()->maxNumberOfAuthorizedClients()
    );
    $service->addModule($oauthModule);
    $service->addModule(new LogoutModule($authModule, $sessionBackend));

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $htmlBody = Tpl::escape('ERROR: '.$e->getMessage());
    if (null !== $tpl) {
        $htmlBody = $tpl->render(
            'errorPage',
            [
                'message' => $e->getMessage(),
                'code' => 500,
            ]
        );
    }
    $response = new HtmlResponse($htmlBody, [], 500);
    $response->send();
}
