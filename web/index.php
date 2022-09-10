<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use fkooman\OAuth\Server\Signer;
use fkooman\SeCookie\Cookie;
use fkooman\SeCookie\CookieOptions;
use Vpn\Portal\Cfg\Config;
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
use Vpn\Portal\Http\Auth\OidcAuthModule;
use Vpn\Portal\Http\Auth\PhpSamlSpAuthModule;
use Vpn\Portal\Http\Auth\RadiusCredentialValidator;
use Vpn\Portal\Http\Auth\ShibAuthModule;
use Vpn\Portal\Http\Auth\UserPassAuthModule;
use Vpn\Portal\Http\CsrfProtectionHook;
use Vpn\Portal\Http\DisabledUserHook;
use Vpn\Portal\Http\LogoutModule;
use Vpn\Portal\Http\OAuthModule;
use Vpn\Portal\Http\PasswdModule;
use Vpn\Portal\Http\PortalService;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Response;
use Vpn\Portal\Http\SeCookie;
use Vpn\Portal\Http\SeSession;
use Vpn\Portal\Http\UpdateUserInfoHook;
use Vpn\Portal\Http\VpnPortalModule;
use Vpn\Portal\HttpClient\CurlHttpClient;
use Vpn\Portal\LogConnectionHook;
use Vpn\Portal\OAuth\ClientDb;
use Vpn\Portal\OAuth\VpnOAuthServer;
use Vpn\Portal\OpenVpn\CA\VpnCa;
use Vpn\Portal\OpenVpn\TlsCrypt;
use Vpn\Portal\ScriptConnectionHook;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;
use Vpn\Portal\SysLogger;
use Vpn\Portal\Tpl;
use Vpn\Portal\Validator;
use Vpn\Portal\VpnDaemon;

// only allow owner permissions
umask(0077);

$logger = new SysLogger('vpn-user-portal');

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $request = Request::createFromGlobals();

    // determine perferred UI language
    if (null === $uiLanguage = $request->getCookie('L', fn (string $s) => Validator::languageCode($s))) {
        $uiLanguage = $config->defaultLanguage();
    }
    $tpl = new Tpl(
        $baseDir,
        $config->styleName(),
        $uiLanguage,
        [
            'requestRoot' => $request->getRoot(),
            'portalHost' => gethostname(),
            'portalVersion' => trim(FileIO::read($baseDir.'/VERSION')),
            'enabledLanguages' => $config->enabledLanguages(),
            'authModule' => $config->authModule(),
            'isAdmin' => false,
            'showLogoutButton' => true,
            'portalNumber' => $config->portalNumber(),
        ]
    );

    $dateTime = Dt::get();
    FileIO::mkdir($baseDir.'/data');
    $ca = new VpnCa($baseDir.'/config/keys/ca', $config->vpnCaPath());
    $sessionExpiry = Expiry::calculate(
        $dateTime,
        $ca->caCert()->validTo(),
        $config->sessionExpiry()
    );

    if ($dateTime->add(new DateInterval('PT30M')) >= $dateTime->add($sessionExpiry)) {
        throw new RuntimeException('sessionExpiry MUST be > PT30M');
    }
    $storage = new Storage($config->dbConfig($baseDir));

    // XXX do we need to set the path?
    $cookieOptions = CookieOptions::init()->withPath($request->getRoot());
    $cookieBackend = new SeCookie(new Cookie($cookieOptions->withMaxAge(60 * 60 * 24 * 90)->withSameSiteLax()));
    $sessionBackend = new SeSession($cookieOptions->withSameSiteStrict(), $config);

    switch ($config->authModule()) {
        case 'DbAuthModule':
            $dbCredentialStorage = new DbCredentialValidator($storage);
            $authModule = new UserPassAuthModule($dbCredentialStorage, $sessionBackend, $tpl, $logger);

            break;

        case 'ClientCertAuthModule':
            $authModule = new ClientCertAuthModule();

            break;

        case 'LdapAuthModule':
            $authModule = new UserPassAuthModule(
                new LdapCredentialValidator(
                    $config->ldapAuthConfig(),
                    $logger
                ),
                $sessionBackend,
                $tpl,
                $logger
            );

            break;

        case 'RadiusAuthModule':
            $authModule = new UserPassAuthModule(
                new RadiusCredentialValidator(
                    $logger,
                    $config->radiusAuthConfig()
                ),
                $sessionBackend,
                $tpl,
                $logger
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

        case 'OidcAuthModule':
            $authModule = new OidcAuthModule($config->OidcAuthConfig());

            break;

        default:
            throw new RuntimeException('unsupported authentication mechanism');
    }

    $service = new PortalService($authModule, $tpl);
    $service->addHook(new CsrfProtectionHook());

    if ('DbAuthModule' === $config->authModule()) {
        $dbCredentialStorage = new DbCredentialValidator($storage);
        // when using local database, users are allowed to change their own
        // password
        $service->addModule(
            new PasswdModule($dbCredentialStorage, $tpl, $storage)
        );
    }

    if (null !== $accessPermissionList = $config->accessPermissionList()) {
        // hasAccess
        $service->addHook(new AccessHook($accessPermissionList));
    }

    $service->addHook(new DisabledUserHook($storage));
    $service->addHook(new UpdateUserInfoHook($sessionBackend, $storage, $authModule));

    // isAdmin
    $adminHook = new AdminHook(
        $config->adminPermissionList(),
        $config->adminUserIdList(),
        $tpl
    );

    $service->addHook($adminHook);
    $oauthClientDb = new ClientDb();
    $oauthStorage = new OAuthStorage($storage->dbPdo(), 'oauth_');
    $oauthKey = FileIO::read($baseDir.'/config/keys/oauth.key');
    $oauthSigner = new Signer($oauthKey);
    $serverInfo = new ServerInfo(
        $request->getRootUri(),
        $baseDir.'/data/keys',
        $ca,
        new TlsCrypt($baseDir.'/data/keys'),
        $config->wireGuardConfig()->listenPort(),
        Signer::publicKeyFromSecretKey($oauthKey)
    );

    $vpnDaemon = new VpnDaemon(new CurlHttpClient($baseDir.'/config/keys/vpn-daemon'), $logger);
    $connectionManager = new ConnectionManager($config, $vpnDaemon, $storage, $logger);
    if ($config->logConfig()->syslogConnectionEvents()) {
        $connectionManager->addConnectionHook(new LogConnectionHook($logger, $config->logConfig()));
    }
    if (null !== $connectScriptPath = $config->connectScriptPath()) {
        $connectionManager->addConnectionHook(new ScriptConnectionHook($connectScriptPath));
    }

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
        $oauthServer,
        $tpl
    );
    $service->addModule($oauthModule);
    $service->addModule(new LogoutModule($authModule, $sessionBackend));

    $htmlResponse = $service->run($request);
    $sessionBackend->stop();
    $htmlResponse->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new Response($e->getMessage(), ['Content-Type' => 'text/plain'], 500);
    $response->send();
}
