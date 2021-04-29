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
use LC\Portal\CA\CaInterface;
use LC\Portal\ClientConfig;
use LC\Portal\Config;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\LoggerInterface;
use LC\Portal\OpenVpn\DaemonWrapper;
use LC\Portal\ProfileConfig;
use LC\Portal\RandomInterface;
use LC\Portal\Storage;
use LC\Portal\TlsCrypt;
use LC\Portal\TplInterface;

class VpnPortalModule implements ServiceModuleInterface
{
    private Config $config;
    private TplInterface $tpl;
    private CookieInterface $cookie;
    private SessionInterface $session;
    private DaemonWrapper $daemonWrapper;
    private Storage $storage;
    private OAuthStorage $oauthStorage;
    private TlsCrypt $tlsCrypt;
    private RandomInterface $random;
    private CaInterface $ca;
    private ClientDbInterface $clientDb;
    private DateInterval $sessionExpiry;
    private DateTimeImmutable $dateTime;

    public function __construct(Config $config, TplInterface $tpl, CookieInterface $cookie, SessionInterface $session, DaemonWrapper $daemonWrapper, Storage $storage, OAuthStorage $oauthStorage, TlsCrypt $tlsCrypt, RandomInterface $random, CaInterface $ca, ClientDbInterface $clientDb, DateInterval $sessionExpiry)
    {
        $this->config = $config;
        $this->tpl = $tpl;
        $this->cookie = $cookie;
        $this->session = $session;
        $this->storage = $storage;
        $this->oauthStorage = $oauthStorage;
        $this->daemonWrapper = $daemonWrapper;
        $this->tlsCrypt = $tlsCrypt;
        $this->random = $random;
        $this->ca = $ca;
        $this->clientDb = $clientDb;
        $this->sessionExpiry = $sessionExpiry;
        $this->dateTime = new DateTimeImmutable();
    }

    public function setDateTime(DateTimeImmutable $dateTime): void
    {
        $this->dateTime = $dateTime;
    }

    public function init(Service $service): void
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
                if (!$this->config->requireBool('enableConfigDownload', true)) {
                    throw new HttpException('downloading configuration files has been disabled by the admin', 403);
                }

                $profileList = $this->profileList();
                $userPermissions = $userInfo->permissionList();
                $visibleProfileList = self::filterProfileList($profileList, $userPermissions);

                $userCertificateList = $this->storage->getCertificates($userInfo->userId());

                // if query parameter "all" is set, show all certificates, also
                // those issued to OAuth clients
                $showAll = null !== $request->optionalQueryParameter('all');

                $manualCertificateList = [];
                if (false === $showAll) {
                    foreach ($userCertificateList as $userCertificate) {
                        if (null === $userCertificate['client_id']) {
                            $manualCertificateList[] = $userCertificate;
                        }
                    }
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalConfigurations',
                        [
                            'disableConfigDownload' => $this->config->requireBool('disableConfigDownload', false),
                            'expiryDate' => $this->dateTime->add($this->sessionExpiry)->format('Y-m-d'),
                            'profileList' => $visibleProfileList,
                            'userCertificateList' => $showAll ? $userCertificateList : $manualCertificateList,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/configurations',
            function (UserInfo $userInfo, Request $request): Response {
                if (!$this->config->requireBool('enableConfigDownload', true)) {
                    throw new HttpException('downloading configuration files has been disabled by the admin', 403);
                }

                $displayName = InputValidation::displayName($request->requirePostParameter('displayName'));
                $profileId = InputValidation::profileId($request->requirePostParameter('profileId'));

                $profileList = $this->profileList();
                $userPermissions = $userInfo->permissionList();
                $visibleProfileList = self::filterProfileList($profileList, $userPermissions);

                // make sure the profileId is in the list of allowed profiles for this
                // user, it would not result in the ability to use the VPN, but
                // better prevent it early
                if (!\array_key_exists($profileId, $visibleProfileList)) {
                    throw new HttpException('no permission to download a configuration for this profile', 403);
                }

                $expiresAt = $this->dateTime->add($this->sessionExpiry);

                return $this->getConfig($request->getServerName(), $profileId, $userInfo->userId(), $displayName, $expiresAt);
            }
        );

        $service->post(
            '/deleteCertificate',
            function (UserInfo $userInfo, Request $request): Response {
                $commonName = InputValidation::commonName($request->requirePostParameter('commonName'));
                // XXX make sure certificate belongs to currently logged in user
                if (false === $certInfo = $this->storage->getUserCertificateInfo($commonName)) {
                    throw new HttpException('certificate does not exist', 400);
                }

                $this->storage->addUserLog(
                    $certInfo['user_id'],
                    LoggerInterface::NOTICE,
                    sprintf('certificate "%s" deleted by user', $certInfo['display_name']),
                    $this->dateTime
                );

                $this->storage->deleteCertificate($commonName);
                $this->daemonWrapper->killClient($commonName);

                return new RedirectResponse($request->getRootUri().'configurations', 302);
            }
        );

        $service->get(
            '/account',
            function (UserInfo $userInfo, Request $request): Response {
                $userPermissions = $userInfo->permissionList();
                $authorizationList = $this->oauthStorage->getAuthorizations($userInfo->userId());
                $authorizedClientInfoList = [];
                foreach ($authorizationList as $authorization) {
                    if (null !== $clientInfo = $this->clientDb->get($authorization->clientId())) {
                        $authorizedClientInfoList[] = [
                            'auth_key' => $authorization->authKey(),
                            'client_id' => $authorization->clientId(),
                            'display_name' => null !== $clientInfo->displayName() ? $clientInfo->displayName() : $authorization->clientId(),
                        ];
                    }
                }
                $userMessages = $this->storage->getUserLog($userInfo->userId());
                $userConnectionLogEntries = $this->storage->getConnectionLogForUser($userInfo->userId());

                // get the fancy profile name
                $profileList = $this->profileList();

                $idNameMapping = [];
                foreach ($profileList as $profileId => $profileConfig) {
                    $idNameMapping[$profileId] = $profileConfig->displayName();
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalAccount',
                        [
                            'userInfo' => $userInfo,
                            'userPermissions' => $userPermissions,
                            'authorizedClientInfoList' => $authorizedClientInfoList,
                            'userMessages' => $userMessages,
                            'userConnectionLogEntries' => $userConnectionLogEntries,
                            'idNameMapping' => $idNameMapping,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/removeClientAuthorization',
            function (UserInfo $userInfo, Request $request): Response {
                // no need to validate the input as we do a strict string match...
                $authKey = $request->requirePostParameter('auth_key');
                $clientId = InputValidation::clientId($request->requirePostParameter('client_id'));

                // verify whether the user_id owns the specified auth_key
                $authorizations = $this->oauthStorage->getAuthorizations($userInfo->userId());

                $authKeyFound = false;
                foreach ($authorizations as $authorization) {
                    if ($authorization->authKey() === $authKey && $authorization->clientId() === $clientId) {
                        $authKeyFound = true;
                        $this->oauthStorage->deleteAuthorization($authKey);
                    }
                }

                if (!$authKeyFound) {
                    throw new HttpException('specified "auth_key" is either invalid or does not belong to this user', 400);
                }

                // get a list of connections for this user_id with the
                // particular client_id
                // NOTE: we have to get the list first before deleting the
                // certificates, otherwise the clients no longer show up the
                // list... this is NOT good, possible race condition...
                $connectionList = $this->daemonWrapper->getConnectionList($clientId, $userInfo->userId());

                // delete the certificates from the server
                $this->storage->addUserLog(
                    $userInfo->userId(),
                    LoggerInterface::NOTICE,
                    sprintf('certificate(s) for OAuth client "%s" deleted', $clientId),
                    $this->dateTime
                );

                $this->storage->deleteCertificatesOfClientId($userInfo->userId(), $clientId);

                // kill all active connections for this user/client_id
                foreach ($connectionList as $profileId => $clientConnectionList) {
                    foreach ($clientConnectionList as $clientInfo) {
                        $this->daemonWrapper->killClient($clientInfo['common_name']);
                    }
                }

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
                $this->cookie->set('L', $request->requirePostParameter('uiLanguage'));

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

    private function getConfig(string $serverName, string $profileId, string $userId, string $displayName, DateTimeImmutable $expiresAt): Response
    {
        // create a certificate
        // generate a random string as the certificate's CN
        $commonName = $this->random->get(16);
        $certInfo = $this->ca->clientCert($commonName, $expiresAt);
        $this->storage->addCertificate(
            $userId,
            $commonName,
            $displayName,
            new DateTimeImmutable(sprintf('@%d', $certInfo['valid_from'])),
            new DateTimeImmutable(sprintf('@%d', $certInfo['valid_to'])),
            null
        );

        $this->storage->addUserLog(
            $userId,
            LoggerInterface::NOTICE,
            sprintf('new certificate "%s" generated by user', $displayName),
            $this->dateTime
        );

        $profileList = $this->profileList();
        $profileConfig = $profileList[$profileId];

        // get the CA & tls-crypt
        $serverInfo = [
            'tls_crypt' => $this->tlsCrypt->get($profileId),
            'ca' => $this->ca->caCert(),
        ];

        $clientConfig = ClientConfig::get($profileConfig, $serverInfo, $certInfo, ClientConfig::STRATEGY_RANDOM);

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
    }

    /**
     * Filter the list of profiles by checking if the profile should be shown,
     * and that the user has the required permissions in case ACLs are enabled.
     *
     * @param array<string,\LC\Portal\ProfileConfig> $profileList
     *
     * @return array<string,\LC\Portal\ProfileConfig>
     */
    private static function filterProfileList(array $profileList, array $userPermissions): array
    {
        $filteredProfileList = [];
        foreach ($profileList as $profileId => $profileConfig) {
            if ($profileConfig->hideProfile()) {
                continue;
            }
            if ($profileConfig->enableAcl()) {
                // is the user member of the aclPermissionList?
                if (!self::isMember($profileConfig->aclPermissionList(), $userPermissions)) {
                    continue;
                }
            }

            $filteredProfileList[$profileId] = $profileConfig;
        }

        return $filteredProfileList;
    }

    /**
     * XXX duplicate in AdminPortalModule|VpnApiModule.
     *
     * @return array<string,\LC\Portal\ProfileConfig>
     */
    private function profileList(): array
    {
        $profileList = [];
        foreach ($this->config->requireArray('vpnProfiles') as $profileId => $profileData) {
            $profileConfig = new ProfileConfig(new Config($profileData));
            $profileList[$profileId] = $profileConfig;
        }

        return $profileList;
    }
}
