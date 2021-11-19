<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateInterval;
use DateTimeImmutable;
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use LC\Portal\Config;
use LC\Portal\ConnectionManager;
use LC\Portal\Dt;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\ServerInfo;
use LC\Portal\Storage;
use LC\Portal\Tpl;
use LC\Portal\TplInterface;
use LC\Portal\Validator;

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
    private DateInterval $sessionExpiry;

    public function __construct(Config $config, TplInterface $tpl, CookieInterface $cookie, ConnectionManager $connectionManager, Storage $storage, OAuthStorage $oauthStorage, ServerInfo $serverInfo, DateInterval $sessionExpiry)
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
            fn (UserInfo $userInfo, Request $request): Response => new RedirectResponse($request->getRootUri().'home', 302)
        );

        $service->get(
            '/home',
            function (UserInfo $userInfo, Request $request): Response {
                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalHome',
                        []
                    )
                );
            }
        );

        $service->get(
            '/configurations',
            function (UserInfo $userInfo, Request $request): Response {
                if (!$this->config->enableConfigDownload()) {
                    throw new HttpException('downloading configuration files has been disabled by the admin', 403);
                }

                $profileConfigList = $this->config->profileConfigList();
                $visibleProfileList = self::filterProfileList($profileConfigList, $userInfo->permissionList());

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalConfigurations',
                        [
                            'profileConfigList' => $visibleProfileList,
                            'expiryDate' => $this->dateTime->add($this->sessionExpiry)->format('Y-m-d'),
                            'configList' => $this->filterConfigList($visibleProfileList, $userInfo->userId()),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/configurations',
            function (UserInfo $userInfo, Request $request): Response {
                if (!$this->config->enableConfigDownload()) {
                    throw new HttpException('downloading configuration files has been disabled by the admin', 403);
                }

                if ($this->config->maxNumberOfActivePortalConfigurations() <= $this->storage->numberOfActivePortalConfigurations($userInfo->userId())) {
                    throw new HttpException('only '.$this->config->maxNumberOfActivePortalConfigurations().' active portal VPN configurations at a time allowed', 403);
                }

                $displayName = $request->requirePostParameter('displayName', fn (string $s) => Validator::displayName($s));
                $tcpOnly = 'on' === $request->optionalPostParameter('tcpOnly', fn (string $s) => Validator::onOrOff($s));
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
                $expiresAt = $this->dateTime->add($this->sessionExpiry);
                if ('default' === $vpnProto = $request->requirePostParameter('vpnProto', fn (string $s) => Validator::vpnProto($s))) {
                    $vpnProto = $profileConfig->preferredProto();
                }

                if ('openvpn' === $vpnProto && $profileConfig->oSupport()) {
                    return $this->getOpenVpnConfig($request->getServerName(), $profileId, $userInfo->userId(), $displayName, $expiresAt, $tcpOnly);
                }

                if ('wireguard' === $vpnProto && $profileConfig->wSupport()) {
                    return $this->getWireGuardConfig($request->getServerName(), $profileId, $userInfo->userId(), $displayName, $expiresAt);
                }

                throw new HttpException(sprintf('profile "%s" does not support protocol "%s"', $profileId, $vpnProto), 400);
            }
        );

        $service->post(
            '/deleteConfig',
            function (UserInfo $userInfo, Request $request): Response {
                $this->connectionManager->disconnect(
                    $userInfo->userId(),
                    $request->requirePostParameter('profileId', fn (string $s) => Validator::profileId($s)),
                    $request->requirePostParameter('connectionId', fn (string $s) => Validator::connectionId($s))
                );

                return new RedirectResponse($request->getRootUri().'configurations', 302);
            }
        );

        $service->get(
            '/account',
            function (UserInfo $userInfo, Request $request): Response {
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
                            'userMessages' => $this->storage->userLog($userInfo->userId()),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/removeClientAuthorization',
            function (UserInfo $userInfo, Request $request): Response {
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

                return new RedirectResponse($request->getRootUri().'account', 302);
            }
        );

        $service->postBeforeAuth(
            '/setLanguage',
            function (Request $request): Response {
                $this->cookie->set('L', $request->requirePostParameter('uiLanguage', fn (string $s) => Validator::inSet($s, array_keys(Tpl::supportedLanguages()))));

                return new RedirectResponse($request->requireHeader('HTTP_REFERER'), 302);
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

    private function getWireGuardConfig(string $serverName, string $profileId, string $userId, string $displayName, DateTimeImmutable $expiresAt): Response
    {
        // XXX we don't do anything with serverName?
        $clientConfig = $this->connectionManager->connect(
            $this->serverInfo,
            $userId,
            $profileId,
            'wireguard',
            $displayName,
            $expiresAt,
            false,
            null,
            null
        );

        return new HtmlResponse(
            $this->tpl->render(
                'vpnPortalWgConfig',
                [
                    'wireGuardClientConfig' => $clientConfig,
                ]
            )
        );
    }

    private function getOpenVpnConfig(string $serverName, string $profileId, string $userId, string $displayName, DateTimeImmutable $expiresAt, bool $tcpOnly): Response
    {
        // XXX we don't do anything with serverName?
        $clientConfig = $this->connectionManager->connect(
            $this->serverInfo,
            $userId,
            $profileId,
            'openvpn',
            $displayName,
            $expiresAt,
            $tcpOnly,
            null,
            null
        );

        // special characters don't work in file names as NetworkManager
        // URL encodes the filename when searching for certificates
        // https://bugzilla.gnome.org/show_bug.cgi?id=795601
        $clientConfigFile = sprintf('%s_%s_%s_%s', $serverName, $profileId, date('Ymd'), str_replace(' ', '_', $displayName));

        return new Response(
            $clientConfig->get(),
            [
                'Content-Type' => $clientConfig->contentType(),
                'Content-Disposition' => sprintf('attachment; filename="%s.ovpn"', $clientConfigFile),
            ]
        );
    }

    /**
     * Filter the list of profiles by checking if the profile should be shown,
     * and that the user has the required permissions in case ACLs are enabled.
     *
     * @param array<\LC\Portal\ProfileConfig> $profileConfigList
     *
     * @return array<\LC\Portal\ProfileConfig>
     */
    private static function filterProfileList(array $profileConfigList, array $userPermissions): array
    {
        $filteredProfileConfigList = [];
        foreach ($profileConfigList as $profileConfig) {
            if ($profileConfig->enableAcl()) {
                // is the user member of the aclPermissionList?
                if (!self::isMember($profileConfig->aclPermissionList(), $userPermissions)) {
                    continue;
                }
            }

            $filteredProfileConfigList[] = $profileConfig;
        }

        return $filteredProfileConfigList;
    }

    /**
     * @param array<\LC\Portal\ProfileConfig> $profileConfigList
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
     * @param array<string,\LC\Portal\ProfileConfig> $profileConfigList
     *
     * @return array<array{profile_id:string,display_name:string,profile_display_name:string,expires_at:\DateTimeImmutable,public_key:?string,common_name:?string}>
     */
    private function filterConfigList(array $profileConfigList, string $userId): array
    {
        $configList = $this->storage->oCertListByUserId($userId);
        $configList = array_merge($configList, $this->storage->wPeerListByUserId($userId));

        $filteredConfigList = [];
        foreach ($configList as $c) {
            if (null !== $c['auth_key']) {
                continue;
            }
            $profileId = $c['profile_id'];

            $filteredConfigList[] = [
                'profile_id' => $profileId,
                'profile_display_name' => \array_key_exists($profileId, $profileConfigList) ? $profileConfigList[$profileId]->displayName() : $profileId,
                'display_name' => $c['display_name'],
                'expires_at' => $c['expires_at'],
                'public_key' => $c['public_key'] ?? null,
                'common_name' => $c['common_name'] ?? null,
            ];
        }

        return $filteredConfigList;
    }
}
