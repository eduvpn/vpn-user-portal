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
use fkooman\OAuth\Server\ClientDbInterface;
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use LC\Portal\Config;
use LC\Portal\ConnectionManager;
use LC\Portal\Dt;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\LoggerInterface;
use LC\Portal\OpenVpn\CA\CaInterface;
use LC\Portal\OpenVpn\ClientConfig;
use LC\Portal\OpenVpn\Exception\ClientConfigException;
use LC\Portal\OpenVpn\TlsCrypt;
use LC\Portal\RandomInterface;
use LC\Portal\Storage;
use LC\Portal\Tpl;
use LC\Portal\TplInterface;
use LC\Portal\Validator;
use LC\Portal\WireGuard\Wg;

class VpnPortalModule implements ServiceModuleInterface
{
    private Config $config;
    private TplInterface $tpl;
    private CookieInterface $cookie;
    private SessionInterface $session;
    private ConnectionManager $connectionManager;
    private Wg $wg;
    private Storage $storage;
    private OAuthStorage $oauthStorage;
    private TlsCrypt $tlsCrypt;
    private RandomInterface $random;
    private CaInterface $ca;
    private ClientDbInterface $clientDb;
    private DateInterval $sessionExpiry;
    private DateTimeImmutable $dateTime;

    public function __construct(Config $config, TplInterface $tpl, CookieInterface $cookie, SessionInterface $session, ConnectionManager $connectionManager, Wg $wg, Storage $storage, OAuthStorage $oauthStorage, TlsCrypt $tlsCrypt, RandomInterface $random, CaInterface $ca, ClientDbInterface $clientDb, DateInterval $sessionExpiry)
    {
        $this->config = $config;
        $this->tpl = $tpl;
        $this->cookie = $cookie;
        $this->session = $session;
        $this->storage = $storage;
        $this->oauthStorage = $oauthStorage;
        $this->connectionManager = $connectionManager;
        $this->wg = $wg;
        $this->tlsCrypt = $tlsCrypt;
        $this->random = $random;
        $this->ca = $ca;
        $this->clientDb = $clientDb;
        $this->sessionExpiry = $sessionExpiry;
        $this->dateTime = Dt::get();
    }

    public function setDateTime(DateTimeImmutable $dateTime): void
    {
        $this->dateTime = $dateTime;
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

                $displayName = $request->requirePostParameter('displayName', fn (string $s) => Validator::displayName($s));
                $tcpOnly = 'on' === $request->optionalPostParameter('tcpOnly', fn (string $s) => \in_array($s, ['on', 'off'], true));
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

                switch ($profileConfig->vpnProto()) {
                    case 'openvpn':
                        return $this->getOpenVpnConfig($request->getServerName(), $profileId, $userInfo->userId(), $displayName, $expiresAt, $tcpOnly);

                    case 'wireguard':
                        return $this->getWireGuardConfig($request->getServerName(), $profileId, $userInfo->userId(), $displayName, $expiresAt);

                    default:
                        throw new HttpException('unsupported VPN type', 500);
                }
            }
        );

        $service->post(
            '/deleteConfig',
            function (UserInfo $userInfo, Request $request): Response {
                $this->connectionManager->disconnect(
                    $userInfo->userId(),
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
                            'userMessages' => $this->storage->getUserLog($userInfo->userId()),
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

        $service->get(
            '/documentation',
            function (): Response {
                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalDocumentation',
                        []
                    )
                );
            }
        );

        $service->postBeforeAuth(
            '/setLanguage',
            function (Request $request): Response {
                $this->cookie->set('L', $request->requirePostParameter('uiLanguage', fn (string $s) => \array_key_exists($s, Tpl::supportedLanguages())));

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
        // XXX take ProfileConfig as a parameter...
        $profileConfig = $this->config->profileConfig($profileId);
        $wgConfig = $this->wg->addPeer(
            $profileConfig,
            $userId,
            $displayName,
            $expiresAt,
            null,
            null
        );

        return new HtmlResponse(
            $this->tpl->render(
                'vpnPortalWgConfig',
                [
                    'wgConfig' => (string) $wgConfig,
                ]
            )
        );
    }

    // XXX merge this with the one in VpnApiThreeModule!
    private function getOpenVpnConfig(string $serverName, string $profileId, string $userId, string $displayName, DateTimeImmutable $expiresAt, bool $tcpOnly): Response
    {
        try {
            // XXX take ProfileConfig as a parameter...
            // create a certificate
            // generate a random string as the certificate's CN
            $commonName = $this->random->get(32);
            $certInfo = $this->ca->clientCert($commonName, $profileId, $expiresAt);
            $this->storage->addCertificate(
                $userId,
                $profileId,
                $commonName,
                $displayName,
                $expiresAt,
                null
            );

            $this->storage->addUserLog(
                $userId,
                LoggerInterface::NOTICE,
                sprintf('new certificate "%s" generated by user', $displayName),
                $this->dateTime
            );

            $profileConfig = $this->config->profileConfig($profileId);
            $clientConfig = ClientConfig::get($profileConfig, $this->ca->caCert(), $this->tlsCrypt, $certInfo, ClientConfig::STRATEGY_RANDOM, $tcpOnly);

            // convert the OpenVPN file to "Windows" format, no platform cares, but
            // in Notepad on Windows it looks not so great everything on one line
            $clientConfig = str_replace("\n", "\r\n", $clientConfig);

            // XXX consider the timezone in the data call, this will be weird
            // when not using same timezone as user machine...

            // special characters don't work in file names as NetworkManager
            // URL encodes the filename when searching for certificates
            // https://bugzilla.gnome.org/show_bug.cgi?id=795601
            $displayName = str_replace(' ', '_', $displayName);

            $clientConfigFile = sprintf('%s_%s_%s_%s', $serverName, $profileId, date('Ymd'), $displayName);

            return new Response(
                $clientConfig,
                [
                    'Content-Type' => 'application/x-openvpn-profile',
                    'Content-Disposition' => sprintf('attachment; filename="%s.ovpn"', $clientConfigFile),
                ]
            );
        } catch (ClientConfigException $e) {
            throw new HttpException($e->getMessage(), 406);
        }
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
            if ($profileConfig->hideProfile()) {
                continue;
            }
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
        $configList = $this->storage->getCertificates($userId);
        $configList = array_merge($configList, $this->storage->wgGetPeers($userId));

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
