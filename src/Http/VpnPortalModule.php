<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use DateTimeImmutable;
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ClientConfigInterface;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\Dt;
use Vpn\Portal\Expiry;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\OpenVpn\ClientConfig as OpenVpnClientConfig;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;
use Vpn\Portal\Tpl;
use Vpn\Portal\TplInterface;
use Vpn\Portal\Validator;
use Vpn\Portal\WireGuard\ClientConfig as WireGuardClientConfig;
use Vpn\Portal\WireGuard\Key;

class VpnPortalModule implements ServiceModuleInterface
{
    protected DateTimeImmutable $dateTime;
    private Config $config;
    private TplInterface $tpl;
    private CookieInterface $cookie;
    private ConnectionManager $connectionManager;
    private Storage $storage;
    private OAuthStorage $oauthStorage;
    private ServerInfo $serverInfo;
    private Expiry $sessionExpiry;

    public function __construct(Config $config, TplInterface $tpl, CookieInterface $cookie, ConnectionManager $connectionManager, Storage $storage, OAuthStorage $oauthStorage, ServerInfo $serverInfo, Expiry $sessionExpiry)
    {
        $this->config = $config;
        $this->tpl = $tpl;
        $this->cookie = $cookie;
        $this->storage = $storage;
        $this->oauthStorage = $oauthStorage;
        $this->serverInfo = $serverInfo;
        $this->connectionManager = $connectionManager;
        $this->sessionExpiry = $sessionExpiry;
        $this->dateTime = Dt::get();
    }

    public function init(ServiceInterface $service): void
    {
        $service->get(
            '/',
            fn (Request $request, UserInfo $userInfo): Response => new RedirectResponse($request->getRootUri().'home')
        );

        $service->get(
            '/home',
            function (Request $request, UserInfo $userInfo): Response {
                $profileConfigList = $this->config->profileConfigList();
                $visibleProfileList = self::filterProfileList($profileConfigList, $userInfo->permissionList());

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalHome',
                        [
                            'maxActiveConfigurations' => $this->config->maxActiveConfigurations(),
                            'numberOfActivePortalConfigurations' => $this->storage->numberOfActivePortalConfigurations($userInfo->userId(), $this->dateTime),
                            'profileConfigList' => $visibleProfileList,
                            'expiryDate' => $this->sessionExpiry->expiresAt()->format('Y-m-d'),
                            'configList' => self::filterConfigList($this->storage, $userInfo->userId()),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/addConfig',
            function (Request $request, UserInfo $userInfo): Response {
                $maxActiveConfigurations = $this->config->maxActiveConfigurations();
                if (0 === $maxActiveConfigurations) {
                    throw new HttpException('no portal configuration downloads allowed', 403);
                }
                $numberOfActivePortalConfigurations = $this->storage->numberOfActivePortalConfigurations($userInfo->userId(), $this->dateTime);
                if ($numberOfActivePortalConfigurations >= $maxActiveConfigurations) {
                    throw new HttpException('limit of available portal configuration downloads has been reached', 403);
                }

                $displayName = $request->requirePostParameter('displayName', fn (string $s) => Validator::displayName($s));
                $preferTcp = 'on' === $request->optionalPostParameter('preferTcp', fn (string $s) => Validator::onOrOff($s));
                $profileId = $request->requirePostParameter('profileId', fn (string $s) => Validator::profileId($s));
                $profileConfigList = $this->config->profileConfigList();
                $userPermissions = $userInfo->permissionList();
                $visibleProfileList = self::filterProfileList($profileConfigList, $userPermissions);

                // make sure the profileId is in the list of allowed profiles for this
                // user, it would not result in the ability to use the VPN, but
                // better prevent it early
                if (!$this->isAllowedProfile($visibleProfileList, $profileId)) {
                    throw new HttpException('no permission to download a configuration for this profile', 403);
                }

                $profileConfig = $this->config->profileConfig($profileId);
                $secretKey = Key::generate();
                $publicKey = Key::publicKeyFromSecretKey($secretKey);

                $clientProtoSupport = self::clientProtoSupport($request->requirePostParameter('useProto', fn (string $s) => Validator::vpnProto($s)));
                $clientConfig = $this->connectionManager->connect(
                    $this->serverInfo,
                    $profileConfig,
                    $userInfo->userId(),
                    $clientProtoSupport,
                    $displayName,
                    $this->sessionExpiry->expiresAt(),
                    $preferTcp,
                    $publicKey,
                    null
                );

                return self::clientConfigResponse($clientConfig, $request->getServerName(), $profileId, $displayName, $secretKey);
            }
        );

        $service->post(
            '/deleteConfig',
            function (Request $request, UserInfo $userInfo): Response {
                $this->connectionManager->disconnectByConnectionId(
                    $userInfo->userId(),
                    $request->requirePostParameter('connectionId', fn (string $s) => Validator::connectionId($s))
                );

                return new RedirectResponse($request->getRootUri().'home');
            }
        );

        $service->get(
            '/account',
            function (Request $request, UserInfo $userInfo): Response {
                $profileConfigList = $this->config->profileConfigList();
                $visibleProfileList = self::filterProfileList($profileConfigList, $userInfo->permissionList());

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalAccount',
                        [
                            'profileConfigList' => $visibleProfileList,
                            'showPermissions' => $this->config->showPermissions(),
                            'userInfo' => $userInfo,
                            'authorizationList' => $this->oauthStorage->getAuthorizations($userInfo->userId()),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/removeClientAuthorization',
            function (Request $request, UserInfo $userInfo): Response {
                // XXX make sure authKey belongs to current user!
                $authKey = $request->requirePostParameter('auth_key', fn (string $s) => Validator::authKey($s));
                if (null === $this->oauthStorage->getAuthorization($authKey)) {
                    throw new HttpException('no such authorization', 400);
                }

                // disconnect all OpenVPN and WireGuard clients under this
                // OAuth authorization
                $this->connectionManager->disconnectByAuthKey($authKey);

                // delete the OAuth authorization
                $this->oauthStorage->deleteAuthorization($authKey);

                return new RedirectResponse($request->getRootUri().'account');
            }
        );

        $service->postBeforeAuth(
            '/setLanguage',
            function (Request $request): Response {
                $this->cookie->set('L', $request->requirePostParameter('uiLanguage', fn (string $s) => Validator::inSet($s, array_keys(Tpl::supportedLanguages()))));

                return new RedirectResponse($request->requireReferrer());
            }
        );
    }

    public static function isMember(array $aclPermissionList, array $userPermissions): bool
    {
        // if any of the permissions is part of aclPermissionList return true
        foreach ($userPermissions as $userPermission) {
            if (\in_array($userPermission, $aclPermissionList, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<array{profile_id:string,display_name:string,expires_at:\DateTimeImmutable,connection_id:string}>
     */
    public static function filterConfigList(Storage $storage, string $userId): array
    {
        $configList = [];
        foreach ($storage->oCertInfoListByUserId($userId) as $oCertInfo) {
            if (null !== $oCertInfo['auth_key']) {
                continue;
            }
            $configList[] = array_merge(
                $oCertInfo,
                [
                    'connection_id' => $oCertInfo['common_name'],
                ]
            );
        }

        foreach ($storage->wPeerInfoListByUserId($userId) as $wPeerInfo) {
            if (null !== $wPeerInfo['auth_key']) {
                continue;
            }
            $configList[] = array_merge(
                $wPeerInfo,
                [
                    'connection_id' => $wPeerInfo['public_key'],
                ]
            );
        }

        return $configList;
    }

    private function clientConfigResponse(ClientConfigInterface $clientConfig, string $serverName, string $profileId, string $displayName, string $secretKey): Response
    {
        if ($clientConfig instanceof WireGuardClientConfig) {
            // set private key
            $clientConfig->setPrivateKey($secretKey);

            return new HtmlResponse(
                $this->tpl->render(
                    'vpnPortalWgConfig',
                    [
                        'wireGuardClientConfig' => $clientConfig,
                    ]
                )
            );
        }

        if ($clientConfig instanceof OpenVpnClientConfig) {
            // special characters don't work in file names as NetworkManager
            // URL encodes the filename when searching for certificates
            // https://bugzilla.gnome.org/show_bug.cgi?id=795601
            $clientConfigFile = sprintf('%s_%s_%s_%s', $serverName, $profileId, $this->dateTime->format('Ymd'), str_replace(' ', '_', $displayName));

            return new Response(
                $clientConfig->get(),
                [
                    'Content-Type' => $clientConfig->contentType(),
                    'Content-Disposition' => sprintf('attachment; filename="%s.ovpn"', $clientConfigFile),
                ]
            );
        }

        throw new HttpException('unsupported client config format', 500);
    }

    /**
     * Filter the list of profiles by checking if the profile should be shown,
     * and that the user has the required permissions in case ACLs are enabled.
     *
     * @param array<\Vpn\Portal\Cfg\ProfileConfig> $profileConfigList
     *
     * @return array<\Vpn\Portal\Cfg\ProfileConfig>
     */
    private static function filterProfileList(array $profileConfigList, array $userPermissions): array
    {
        $filteredProfileConfigList = [];
        foreach ($profileConfigList as $profileConfig) {
            if (null !== $aclPermissionList = $profileConfig->aclPermissionList()) {
                // is the user member of the aclPermissionList?
                if (!self::isMember($aclPermissionList, $userPermissions)) {
                    continue;
                }
            }

            $filteredProfileConfigList[] = $profileConfig;
        }

        return $filteredProfileConfigList;
    }

    /**
     * @param array<\Vpn\Portal\Cfg\ProfileConfig> $profileConfigList
     */
    private static function isAllowedProfile(array $profileConfigList, string $profileId): bool
    {
        foreach ($profileConfigList as $profileConfig) {
            if ($profileId === $profileConfig->profileId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{wireguard:bool,openvpn:bool}
     */
    private static function clientProtoSupport(string $useProto): array
    {
        if ('openvpn' === $useProto) {
            return ['wireguard' => false, 'openvpn' => true];
        }
        if ('wireguard' === $useProto) {
            return ['wireguard' => true, 'openvpn' => false];
        }

        return ['wireguard' => true, 'openvpn' => true];
    }
}
