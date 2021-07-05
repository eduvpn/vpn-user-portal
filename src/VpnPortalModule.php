<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateInterval;
use DateTime;
use fkooman\OAuth\Server\ClientDbInterface;
use LC\Common\Config;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\InputValidation;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\Http\SessionInterface;
use LC\Common\HttpClient\ServerClient;
use LC\Common\ProfileConfig;
use LC\Common\TplInterface;

class VpnPortalModule implements ServiceModuleInterface
{
    /** @var \LC\Common\Config */
    private $config;

    /** @var \LC\Common\TplInterface */
    private $tpl;

    /** @var \LC\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \LC\Common\Http\SessionInterface */
    private $session;

    /** @var \LC\Portal\Storage */
    private $storage;

    /** @var \fkooman\OAuth\Server\ClientDbInterface */
    private $clientDb;

    /** @var \DateTime */
    private $dateTime;

    /** @var \DateInterval */
    private $sessionExpiry;

    public function __construct(Config $config, TplInterface $tpl, ServerClient $serverClient, SessionInterface $session, Storage $storage, ClientDbInterface $clientDb, DateInterval $sessionExpiry)
    {
        $this->config = $config;
        $this->tpl = $tpl;
        $this->serverClient = $serverClient;
        $this->session = $session;
        $this->storage = $storage;
        $this->clientDb = $clientDb;
        $this->sessionExpiry = $sessionExpiry;
        $this->dateTime = new DateTime();
    }

    /**
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
             * @return \LC\Common\Http\Response
             */
            function (Request $request) {
                return new RedirectResponse($request->getRootUri().'home', 302);
            }
        );

        $service->get(
            '/home',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $motdMessages = $this->serverClient->getRequireArray('system_messages', ['message_type' => 'motd']);
                if (0 === \count($motdMessages)) {
                    $motdMessage = false;
                } else {
                    $motdMessage = $motdMessages[0];
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalHome',
                        [
                            'motdMessage' => $motdMessage,
                        ]
                    )
                );
            }
        );

        $service->get(
            '/configurations',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $profileList = $this->serverClient->getRequireArray('profile_list');
                $userPermissions = $userInfo->getPermissionList();
                $visibleProfileList = self::getProfileList($profileList, $userPermissions);

                $userCertificateList = $this->serverClient->getRequireArray('client_certificate_list', ['user_id' => $userInfo->getUserId()]);

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
                            'expiryDate' => $this->getExpiryDate($this->sessionExpiry),
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
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $displayName = InputValidation::displayName($request->requirePostParameter('displayName'));
                $profileId = InputValidation::profileId($request->requirePostParameter('profileId'));

                $profileList = $this->serverClient->getRequireArray('profile_list');
                $userPermissions = $userInfo->getPermissionList();
                $visibleProfileList = self::getProfileList($profileList, $userPermissions);

                // make sure the profileId is in the list of allowed profiles for this
                // user, it would not result in the ability to use the VPN, but
                // better prevent it early
                if (!\in_array($profileId, array_keys($visibleProfileList), true)) {
                    throw new HttpException('no permission to download a configuration for this profile', 400);
                }

                $expiresAt = new DateTime($this->serverClient->getRequireString('user_session_expires_at', ['user_id' => $userInfo->getUserId()]));

                return $this->getConfig($request->getServerName(), $profileId, $userInfo->getUserId(), $displayName, $expiresAt);
            }
        );

        $service->post(
            '/deleteCertificate',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $commonName = InputValidation::commonName($request->requirePostParameter('commonName'));
                $this->serverClient->post('delete_client_certificate', ['common_name' => $commonName]);
                $this->serverClient->post('kill_client', ['common_name' => $commonName]);

                return new RedirectResponse($request->getRootUri().'configurations', 302);
            }
        );

        $service->get(
            '/account',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];
                $hasTotpSecret = $this->serverClient->getRequireBool('has_totp_secret', ['user_id' => $userInfo->getUserId()]);
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

                $userMessages = $this->serverClient->getRequireArray('user_messages', ['user_id' => $userInfo->getUserId()]);
                $userConnectionLogEntries = $this->serverClient->getRequireArray('user_connection_log', ['user_id' => $userInfo->getUserId()]);

                // get the fancy profile name
                $profileList = $this->serverClient->getRequireArray('profile_list');
                $visibleProfileList = self::getProfileList($profileList, $userPermissions);

                $idNameMapping = [];
                foreach ($profileList as $profileId => $profileData) {
                    $idNameMapping[$profileId] = $profileData['displayName'];
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalAccount',
                        [
                            'hasTotpSecret' => $hasTotpSecret,
                            'userInfo' => $userInfo,
                            'showPermissions' => $this->config->requireBool('showPermissions', true),
                            'userPermissions' => $userPermissions,
                            'authorizedClients' => $authorizedClients,
                            'twoFactorMethods' => $this->config->requireArray('twoFactorMethods', ['totp']),
                            'userMessages' => $userMessages,
                            'userConnectionLogEntries' => $userConnectionLogEntries,
                            'idNameMapping' => $idNameMapping,
                            'visibleProfileList' => $visibleProfileList,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/removeClientAuthorization',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];

                // no need to validate the input as we do a strict string match...
                $authKey = $request->requirePostParameter('auth_key');
                $clientId = InputValidation::clientId($request->requirePostParameter('client_id'));

                // verify whether the user_id owns the specified auth_key
                $authorizations = $this->storage->getAuthorizations($userInfo->getUserId());

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

                // get a list of connections for this user_id with the
                // particular client_id
                // NOTE: we have to get the list first before deleting the
                // certificates, otherwise the clients no longer show up the
                // list... this is NOT good, possible race condition...
                $connectionList = $this->serverClient->getRequireArray('client_connections', ['client_id' => $clientId, 'user_id' => $userInfo->getUserId()]);

                // delete the certificates from the server
                $this->serverClient->post('delete_client_certificates_of_client_id', ['user_id' => $userInfo->getUserId(), 'client_id' => $clientId]);

                // kill all active connections for this user/client_id
                foreach ($connectionList as $profileId => $clientConnectionList) {
                    foreach ($clientConnectionList as $clientInfo) {
                        $this->serverClient->post('kill_client', ['common_name' => $clientInfo['common_name']]);
                    }
                }

                return new RedirectResponse($request->getRootUri().'account', 302);
            }
        );

        $service->get(
            '/documentation',
            /**
             * @return \LC\Common\Http\Response
             */
            function () {
                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalDocumentation',
                        [
                            'twoFactorMethods' => $this->config->requireArray('twoFactorMethods', ['totp']),
                        ]
                    )
                );
            }
        );
    }

    /**
     * @param string $scope
     *
     * @return string
     */
    public static function validateScope($scope)
    {
        // scope       = scope-token *( SP scope-token )
        // scope-token = 1*NQCHAR
        // NQCHAR      = %x21 / %x23-5B / %x5D-7E
        foreach (explode(' ', $scope) as $scopeToken) {
            if (1 !== preg_match('/^[\x21\x23-\x5B\x5D-\x7E]+$/', $scopeToken)) {
                throw new HttpException('invalid "scope"', 400);
            }
        }

        return $scope;
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
     * @return \LC\Common\Http\Response
     */
    private function getConfig($serverName, $profileId, $userId, $displayName, DateTime $expiresAt)
    {
        // create a certificate
        $clientCertificate = $this->serverClient->postRequireArray(
            'add_client_certificate',
            [
                'user_id' => $userId,
                'display_name' => $displayName,
                'expires_at' => $expiresAt->format(DateTime::ATOM),
            ]
        );

        $serverProfiles = $this->serverClient->getRequireArray('profile_list');
        $profileConfig = new ProfileConfig(new Config($serverProfiles[$profileId]));

        // get the CA & tls-auth
        $serverInfo = $this->serverClient->getRequireArray('server_info', ['profile_id' => $profileId]);

        $clientConfig = ClientConfig::get($profileConfig, $serverInfo, $clientCertificate, ClientConfig::STRATEGY_RANDOM);

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
     * @return array
     */
    private static function getProfileList(array $serverProfiles, array $userPermissions)
    {
        $profileList = [];
        foreach ($serverProfiles as $profileId => $profileData) {
            $profileConfig = new ProfileConfig(new Config($profileData));
            if ($profileConfig->hideProfile()) {
                continue;
            }
            if ($profileConfig->enableAcl()) {
                // is the user member of the aclPermissionList?
                if (!self::isMember($profileConfig->aclPermissionList(), $userPermissions)) {
                    continue;
                }
            }

            $profileList[$profileId] = [
                'displayName' => $profileConfig->displayName(),
            ];
        }

        return $profileList;
    }

    /**
     * @return string
     */
    private function getExpiryDate(DateInterval $dateInterval)
    {
        $expiryDate = date_add(clone $this->dateTime, $dateInterval);

        return $expiryDate->format('Y-m-d');
    }
}
