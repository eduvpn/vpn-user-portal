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
use Vpn\Portal\Config;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\Dt;
use Vpn\Portal\Expiry;
use Vpn\Portal\FileIO;
use Vpn\Portal\Http\AccessHook;
use Vpn\Portal\Http\AdminHook;
use Vpn\Portal\Http\AdminPortalModule;
use Vpn\Portal\Http\Auth\ClientCertAuthModule;
use Vpn\Portal\Http\Auth\DbCredentialValidator;
use Vpn\Portal\Http\Auth\LdapCredentialValidator;
use Vpn\Portal\Http\Auth\MellonAuthModule;
use Vpn\Portal\Http\Auth\PhpSamlSpAuthModule;
use Vpn\Portal\Http\Auth\RadiusCredentialValidator;
use Vpn\Portal\Http\Auth\ShibAuthModule;
use Vpn\Portal\Http\Auth\UserPassAuthModule;
use Vpn\Portal\Http\CsrfProtectionHook;
use Vpn\Portal\Http\DisabledUserHook;
use Vpn\Portal\Http\HtmlResponse;
use Vpn\Portal\Http\LogoutModule;
use Vpn\Portal\Http\OAuthModule;
use Vpn\Portal\Http\PasswdModule;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\SeCookie;
use Vpn\Portal\Http\Service;
use Vpn\Portal\Http\SeSession;
use Vpn\Portal\Http\UpdateUserInfoHook;
use Vpn\Portal\Http\UserPassModule;
use Vpn\Portal\Http\VpnPortalModule;
use Vpn\Portal\HttpClient\CurlHttpClient;
use Vpn\Portal\OAuth\ClientDb;
use Vpn\Portal\OAuth\VpnOAuthServer;
use Vpn\Portal\OpenVpn\CA\VpnCa;
use Vpn\Portal\OpenVpn\TlsCrypt;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;
use Vpn\Portal\SysLogger;
use Vpn\Portal\Tpl;
use Vpn\Portal\VpnDaemon;
use Vpn\Portal\WireGuard\ServerConfig as WireGuardServerConfig;

$logger = new SysLogger('vpn-user-portal');
/** @var ?Vpn\Portal\Tpl $tpl */
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
            'enabledLanguages' => $config->enabledLanguages(),
            'portalVersion' => trim(FileIO::readFile($baseDir.'/VERSION')),
            'isAdmin' => false,
            'uiLanguage' => $uiLanguage,
            '_show_logout_button' => true,
            'authModule' => $config->authModule(),
        ]
    );

    $dateTime = Dt::get();
    FileIO::createDir($baseDir.'/data');
    $ca = new VpnCa($baseDir.'/data/ca', $config->vpnCaPath());
    $sessionExpiry = Expiry::calculate(
        $dateTime,
        $ca->caCert()->validTo(),
        $config->sessionExpiry()
    );

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
    $sessionBackend = new SeSession($cookieOptions->withSameSiteStrict(), $config);

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
            $authModule = new ShibAuthModule($config->shibAuthConfig());

            break;

        case 'MellonAuthModule':
            $authModule = new MellonAuthModule($config->mellonAuthConfig());

            break;

        case 'PhpSamlSpAuthModule':
            $authModule = new PhpSamlSpAuthModule($config->phpSamlSpAuthConfig());

            break;

        default:
            throw new RuntimeException('unsupported authentication mechanism');
    }

    $service->setAuthModule($authModule);

    if (null !== $accessPermissionList = $config->accessPermissionList()) {
        // hasAccess
        $service->addBeforeHook(new AccessHook($accessPermissionList));
    }

    $service->addBeforeHook(new DisabledUserHook($storage));
    $service->addBeforeHook(new UpdateUserInfoHook($sessionBackend, $storage, $authModule));

    // isAdmin
    $adminHook = new AdminHook(
        $config->adminPermissionList(),
        $config->adminUserIdList(),
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
        $oauthSigner,
        $sessionExpiry,
        $config->apiConfig()->tokenExpiry()
    );

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
        $tpl->reset();
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
