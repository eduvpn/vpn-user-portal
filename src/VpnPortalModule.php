<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateInterval;
use DateTimeImmutable;
use fkooman\OAuth\Server\ClientDbInterface;
use LC\Portal\CA\CaInterface;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\Http\HtmlResponse;
use LC\Portal\Http\InputValidation;
use LC\Portal\Http\RedirectResponse;
use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\Service;
use LC\Portal\Http\ServiceModuleInterface;
use LC\Portal\Http\SessionInterface;
use LC\Portal\OpenVpn\DaemonWrapper;

class VpnPortalModule implements ServiceModuleInterface
{
    /** @var \LC\Portal\Config */
    private $config;

    /** @var \LC\Portal\TplInterface */
    private $tpl;

    /** @var \LC\Portal\Http\SessionInterface */
    private $session;

    /** @var OpenVpn\DaemonWrapper */
    private $daemonWrapper;

    /** @var Storage */
    private $storage;

    /** @var TlsCrypt */
    private $tlsCrypt;

    /** @var \LC\Portal\RandomInterface */
    private $random;

    /** @var CA\CaInterface */
    private $ca;

    /** @var \fkooman\OAuth\Server\ClientDbInterface */
    private $clientDb;

    /** @var \DateTimeImmutable */
    private $dateTime;

    public function __construct(Config $config, TplInterface $tpl, SessionInterface $session, DaemonWrapper $daemonWrapper, Storage $storage, TlsCrypt $tlsCrypt, RandomInterface $random, CaInterface $ca, ClientDbInterface $clientDb)
    {
        $this->config = $config;
        $this->tpl = $tpl;
        $this->session = $session;
        $this->storage = $storage;
        $this->daemonWrapper = $daemonWrapper;
        $this->tlsCrypt = $tlsCrypt;
        $this->random = $random;
        $this->ca = $ca;
        $this->clientDb = $clientDb;
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
            /*
             * @return \LC\Portal\Http\Response
             */
            fn (Request $request) => new RedirectResponse($request->getRootUri().'home', 302)
        );

        $service->get(
            '/home',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
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
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $profileList = $this->profileList();
                $userPermissions = $userInfo->getPermissionList();
                $visibleProfileList = self::filterProfileList($profileList, $userPermissions);

                $userCertificateList = $this->storage->getCertificates($userInfo->getUserId());

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
                            'expiryDate' => $this->getExpiryDate(new DateInterval($this->config->requireString('sessionExpiry', 'P90D'))),
                            'profileList' => $visibleProfileList,
                            'userCertificateList' => $showAll ? $userCertificateList : $manualCertificateList,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/configurations',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $displayName = InputValidation::displayName($request->requirePostParameter('displayName'));
                $profileId = InputValidation::profileId($request->requirePostParameter('profileId'));

                $profileList = $this->profileList();
                $userPermissions = $userInfo->getPermissionList();
                $visibleProfileList = self::filterProfileList($profileList, $userPermissions);

                // make sure the profileId is in the list of allowed profiles for this
                // user, it would not result in the ability to use the VPN, but
                // better prevent it early
                if (!\array_key_exists($profileId, $visibleProfileList)) {
                    throw new HttpException('no permission to download a configuration for this profile', 400);
                }

                $expiresAt = new DateTimeImmutable($this->storage->getSessionExpiresAt($userInfo->getUserId()));

                return $this->getConfig($request->getServerName(), $profileId, $userInfo->getUserId(), $displayName, $expiresAt);
            }
        );

        $service->post(
            '/deleteCertificate',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                $commonName = InputValidation::commonName($request->requirePostParameter('commonName'));
                // XXX make sure certificate belongs to currently logged in user
                if (false === $certInfo = $this->storage->getUserCertificateInfo($commonName)) {
                    throw new HttpException('certificate does not exist', 400);
                }

                $this->storage->addUserMessage(
                    $certInfo['user_id'],
                    'notification',
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
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];
                $userPermissions = $userInfo->getPermissionList();
                $authorizationList = $this->storage->getAuthorizations($userInfo->getUserId());
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
                $userMessages = $this->storage->userMessages($userInfo->getUserId());
                $userConnectionLogEntries = $this->storage->getConnectionLogForUser($userInfo->getUserId());

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
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];

                // no need to validate the input as we do a strict string match...
                $authKey = $request->requirePostParameter('auth_key');
                $clientId = InputValidation::clientId($request->requirePostParameter('client_id'));

                // verify whether the user_id owns the specified auth_key
                $authorizations = $this->storage->getAuthorizations($userInfo->getUserId());

                $authKeyFound = false;
                foreach ($authorizations as $authorization) {
                    if ($authorization->authKey() === $authKey && $authorization->clientId() === $clientId) {
                        $authKeyFound = true;
                        $this->storage->deleteAuthorization($authKey);
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
                $connectionList = $this->daemonWrapper->getConnectionList($clientId, $userInfo->getUserId());

                // delete the certificates from the server
                $this->storage->addUserMessage(
                    $userInfo->getUserId(),
                    'notification',
                    sprintf('certificates for OAuth client "%s" deleted', $clientId),
                    $this->dateTime
                );

                $this->storage->deleteCertificatesOfClientId($userInfo->getUserId(), $clientId);

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
            /**
             * @return \LC\Portal\Http\Response
             */
            function () {
                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalDocumentation',
                        []
                    )
                );
            }
        );
    }

    /**
     * @return bool
     */
    public static function isMember(array $aclPermissionList, array $userPermissions)
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
     * @param string $serverName
     * @param string $profileId
     * @param string $userId
     * @param string $displayName
     *
     * @return \LC\Portal\Http\Response
     */
    private function getConfig($serverName, $profileId, $userId, $displayName, DateTimeImmutable $expiresAt)
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

        $this->storage->addUserMessage(
            $userId,
            'notification',
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

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->addHeader('Content-Disposition', sprintf('attachment; filename="%s.ovpn"', $clientConfigFile));
        $response->setBody($clientConfig);

        return $response;
    }

    /**
     * Filter the list of profiles by checking if the profile should be shown,
     * and that the user has the required permissions in case ACLs are enabled.
     *
     * @param array<string,\LC\Portal\ProfileConfig> $profileList
     *
     * @return array<string,\LC\Portal\ProfileConfig>
     */
    private static function filterProfileList(array $profileList, array $userPermissions)
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
     * @return string
     */
    private function getExpiryDate(DateInterval $dateInterval)
    {
        return $this->dateTime->add($dateInterval)->format('Y-m-d');
    }

    /**
     * XXX duplicate in AdminPortalModule|VpnApiModule.
     *
     * @return array<string,\LC\Portal\ProfileConfig>
     */
    private function profileList()
    {
        $profileList = [];
        foreach ($this->config->requireArray('vpnProfiles') as $profileId => $profileData) {
            $profileConfig = new ProfileConfig(new Config($profileData));
            $profileList[$profileId] = $profileConfig;
        }

        return $profileList;
    }
}
