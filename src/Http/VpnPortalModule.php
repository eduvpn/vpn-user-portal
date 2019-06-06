<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateTime;
use fkooman\OAuth\Server\ClientDbInterface;
use fkooman\SeCookie\SessionInterface;
use LC\Portal\CA\CaInterface;
use LC\Portal\Config\PortalConfig;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\OpenVpn\ClientConfig;
use LC\Portal\OpenVpn\ServerManagerInterface;
use LC\Portal\OpenVpn\TlsCrypt;
use LC\Portal\Random;
use LC\Portal\RandomInterface;
use LC\Portal\Storage;
use LC\Portal\TplInterface;

class VpnPortalModule implements ServiceModuleInterface
{
    /** @var \LC\Portal\Config\PortalConfig */
    private $portalConfig;

    /** @var \LC\Portal\TplInterface */
    private $tpl;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \LC\Portal\Storage */
    private $storage;

    /** @var \LC\Portal\CA\CaInterface */
    private $ca;

    /** @var \LC\Portal\OpenVpn\TlsCrypt */
    private $tlsCrypt;

    /** @var \LC\Portal\OpenVpn\ServerManagerInterface */
    private $serverManager;

    /** @var \fkooman\OAuth\Server\ClientDbInterface */
    private $clientDb;

    /** @var \DateTime */
    private $dateTime;

    /** @var \LC\Portal\RandomInterface */
    private $random;

    /** @var bool */
    private $shuffleHosts = true;

    public function __construct(PortalConfig $portalConfig, TplInterface $tpl, SessionInterface $session, Storage $storage, CaInterface $ca, TlsCrypt $tlsCrypt, ServerManagerInterface $serverManager, ClientDbInterface $clientDb)
    {
        $this->portalConfig = $portalConfig;
        $this->tpl = $tpl;
        $this->session = $session;
        $this->storage = $storage;
        $this->ca = $ca;
        $this->tlsCrypt = $tlsCrypt;
        $this->serverManager = $serverManager;
        $this->clientDb = $clientDb;
        $this->dateTime = new DateTime();
        $this->random = new Random();
    }

    /**
     * @param bool $shuffleHosts
     *
     * @return void
     */
    public function setShuffleHosts($shuffleHosts)
    {
        $this->shuffleHosts = (bool) $shuffleHosts;
    }

    /**
     * @param \LC\Portal\RandomInterface $random
     *
     * @return void
     */
    public function setRandom(RandomInterface $random)
    {
        $this->random = $random;
    }

    /**
     * @param \DateTime $dateTime
     *
     * @return void
     */
    public function setDateTime(DateTime $dateTime)
    {
        $this->dateTime = $dateTime;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request) {
                return new RedirectResponse($request->getRootUri().'new', 302);
            }
        );

        $service->get(
            '/new',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $profileList = $this->portalConfig->getProfileConfigList();
                $userPermissions = $userInfo->getPermissionList();
                $visibleProfileList = self::getProfileList($profileList, $userPermissions);

                $motdMessages = $this->storage->systemMessages('motd');
                if (0 === \count($motdMessages)) {
                    $motdMessage = false;
                } else {
                    $motdMessage = $motdMessages[0];
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalNew',
                        [
                            'profileList' => $visibleProfileList,
                            'motdMessage' => $motdMessage,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/new',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $displayName = InputValidation::displayName($request->requirePostParameter('displayName'));
                $profileId = InputValidation::profileId($request->requirePostParameter('profileId'));

                $profileList = $this->portalConfig->getProfileConfigList();
                $userPermissions = $userInfo->getPermissionList();
                $visibleProfileList = self::getProfileList($profileList, $userPermissions);

                // make sure the profileId is in the list of allowed profiles
                // for this user, it would not result in the ability to use the
                // VPN, but better prevent it early
                if (!\in_array($profileId, array_keys($visibleProfileList), true)) {
                    throw new HttpException('no permission to download a configuration for this profile', 400);
                }

                $expiresAt = new DateTime($this->storage->getSessionExpiresAt($userInfo->getUserId()));

                return $this->getConfig($request->getServerName(), $profileId, $userInfo->getUserId(), $displayName, $expiresAt);
            }
        );

        $service->get(
            '/certificates',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];

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
                        'vpnPortalCertificates',
                        [
                            'userCertificateList' => $showAll ? $userCertificateList : $manualCertificateList,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/deleteCertificate',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];
                $commonName = InputValidation::commonName($request->requirePostParameter('commonName'));

                if (false === $certInfo = $this->storage->getUserCertificateInfo($commonName)) {
                    throw new HttpException('certificate does not exist', 404);
                }

                // make sure the user owns the certificate
                if ($certInfo['user_id'] !== $userInfo->getUserId()) {
                    throw new HttpException('user does not own this certificate', 403);
                }

                $this->storage->addUserMessage(
                    $certInfo['user_id'],
                    'notification',
                    sprintf('certificate "%s" deleted', $certInfo['display_name'])
                );

                // delete the certificate
                $this->storage->deleteCertificate($commonName);

                // disconnect the client(s) using this certificate
                $this->serverManager->kill($commonName);

                return new RedirectResponse($request->getRootUri().'certificates', 302);
            }
        );

        $service->get(
            '/events',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $userMessages = $this->storage->userMessages($userInfo->getUserId());

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalEvents',
                        [
                            'userMessages' => $userMessages,
                        ]
                    )
                );
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
                $hasTotpSecret = false !== $this->storage->getOtpSecret($userInfo->getUserId());
                $userPermissions = $userInfo->getPermissionList();
                $authorizedClients = $this->storage->getAuthorizations($userInfo->getUserId());
                foreach ($authorizedClients as $k => $v) {
                    // false means no longer registered
                    $displayName = false;
                    if (false !== $clientInfo = $this->clientDb->get($v['client_id'])) {
                        // client_id as name in case no 'display_name' is provided
                        $displayName = $v['client_id'];
                        if (null !== $clientInfo->getDisplayName()) {
                            $displayName = $clientInfo->getDisplayName();
                        }
                    }
                    $authorizedClients[$k]['display_name'] = $displayName;
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalAccount',
                        [
                            'hasTotpSecret' => $hasTotpSecret,
                            'userInfo' => $userInfo,
                            'userPermissions' => $userPermissions,
                            'authorizedClients' => $authorizedClients,
                            'twoFactorMethods' => $this->portalConfig->getTwoFactorMethods(),
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
                $userId = $userInfo->getUserId();

                // no need to validate the input as we do a strict string match...
                $authKey = $request->requirePostParameter('auth_key');
                $clientId = InputValidation::clientId($request->requirePostParameter('client_id'));

                // verify whether the user_id owns the specified auth_key
                $authorizations = $this->storage->getAuthorizations($userId);

                $authKeyFound = false;
                foreach ($authorizations as $authorization) {
                    if ($authorization['auth_key'] === $authKey && $authorization['client_id'] === $clientId) {
                        $authKeyFound = true;
                        $this->storage->deleteAuthorization($authKey);
                    }
                }

                if (!$authKeyFound) {
                    throw new HttpException('specified "auth_key" is either invalid or does not belong to this user', 400);
                }

                // look for all connections that use a certificate bound to a
                // particular client_id and user_id and add them to the kill
                // list...
                $killList = [];
                foreach ($this->serverManager->connections() as $clientConnectionList) {
                    foreach ($clientConnectionList as $clientConnection) {
                        if (false !== $certInfo = $this->storage->getUserCertificateInfo($clientConnection['common_name'])) {
                            // if client_id and user_id match...
                            if ($userId === $certInfo['user_id'] && $clientId === $certInfo['client_id']) {
                                // add it to the kill list and do not disconnect
                                // immediately, as we want to "revoke" the
                                // certificates first before disconnecting as
                                // to prevent reconnects before the certificates
                                // are deleted...
                                $killList[] = $clientConnection['common_name'];
                            }
                        }
                        // if the certificate is no longer there, the connection
                        // will be terminated by the periodic cronjob...
                        // XXX is this actually true?
                    }
                }

                $this->storage->addUserMessage(
                    $userId,
                    'notification',
                    sprintf('certificates for OAuth client "%s" deleted', $clientId)
                );

                $this->storage->deleteCertificatesOfClientId($userId, $clientId);

                foreach ($killList as $commonName) {
                    $this->serverManager->kill($commonName);
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
                        [
                            'twoFactorMethods' => $this->portalConfig->getTwoFactorMethods(),
                        ]
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
     * @param string    $serverName
     * @param string    $profileId
     * @param string    $userId
     * @param string    $displayName
     * @param \DateTime $expiresAt
     *
     * @return \LC\Portal\Http\Response
     */
    private function getConfig($serverName, $profileId, $userId, $displayName, DateTime $expiresAt)
    {
        // XXX this is also more or less duplicated in VpnApiModule, try to
        // merge...

        // create a certificate
        // generate a random string as the certificate's CN
        $commonName = $this->random->get(16);
        $clientCertInfo = $this->ca->clientCert($commonName, $expiresAt);

        $this->storage->addCertificate(
            $userId,
            $commonName,
            $displayName,
            $clientCertInfo->getValidFrom(),
            $clientCertInfo->getValidTo(),
            null
        );

        $serverProfiles = $this->portalConfig->getProfileConfigList();
        $profileConfig = $serverProfiles[$profileId];

        // get the CA & tls-auth
        $serverInfo = [
            'tls_crypt' => $this->tlsCrypt->raw(),
            'ca' => $this->ca->caCert(),
        ];

        $clientConfig = ClientConfig::get($profileConfig, $serverInfo, $clientCertInfo, $this->shuffleHosts);

        // convert the OpenVPN file to "Windows" format, no platform cares, but
        // in Notepad on Windows it looks not so great everything on one line
        $clientConfig = str_replace("\n", "\r\n", $clientConfig);

        // XXX consider the timezone in the data call, this will be weird
        // when not using same timezone as user machine...

        // special characters don't work in file names as NetworkManager
        // URL encodes the filename when searching for certificates
        // https://bugzilla.gnome.org/show_bug.cgi?id=795601
        $displayName = str_replace(' ', '_', $displayName);

        $clientConfigFile = sprintf('%s_%s_%s_%s', $serverName, $profileId, $this->dateTime->format('Ymd'), $displayName);

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->addHeader('Content-Disposition', sprintf('attachment; filename="%s.ovpn"', $clientConfigFile));
        $response->setBody($clientConfig);

        return $response;
    }

    /**
     * Filter the list of profiles by checking if the profile should be shown,
     * and that the user has the required permissions in case ACLs are enabled.
     *
     * @return array
     */
    private static function getProfileList(array $profileConfigList, array $userPermissions)
    {
        $profileList = [];
        foreach ($profileConfigList as $profileId => $profileConfig) {
            if ($profileConfig->getHideProfile()) {
                continue;
            }
            if ($profileConfig->getEnableAcl()) {
                // is the user member of the aclPermissionList?
                if (!self::isMember($profileConfig->getAclPermissionList(), $userPermissions)) {
                    continue;
                }
            }

            $profileList[$profileId] = [
                'displayName' => $profileConfig->getDisplayName(),
            ];
        }

        return $profileList;
    }
}
