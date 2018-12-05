<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use DateInterval;
use DateTime;
use fkooman\SeCookie\SessionInterface;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\Exception\HttpException;
use SURFnet\VPN\Common\Http\HtmlResponse;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\TplInterface;

class VpnPortalModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    /** @var \SURFnet\VPN\Common\TplInterface */
    private $tpl;

    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \SURFnet\VPN\Portal\OAuthStorage */
    private $storage;

    /** @var \DateInterval */
    private $sessionExpiry;

    /** @var callable */
    private $getClientInfo;

    /** @var bool */
    private $shuffleHosts = true;

    public function __construct(Config $config, TplInterface $tpl, ServerClient $serverClient, SessionInterface $session, OAuthStorage $storage, DateInterval $sessionExpiry, callable $getClientInfo)
    {
        $this->config = $config;
        $this->tpl = $tpl;
        $this->serverClient = $serverClient;
        $this->session = $session;
        $this->storage = $storage;
        $this->sessionExpiry = $sessionExpiry;
        $this->getClientInfo = $getClientInfo;
    }

    /**
     * @param mixed $shuffleHosts
     *
     * @return void
     */
    public function setShuffleHosts($shuffleHosts)
    {
        $this->shuffleHosts = (bool) $shuffleHosts;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request) {
                return new RedirectResponse($request->getRootUri().'new', 302);
            }
        );

        $service->get(
            '/new',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];

                $profileList = $this->serverClient->getRequireArray('profile_list');
                $userGroups = $this->cachedUserGroups($userInfo->id());
                $visibleProfileList = self::getProfileList($profileList, $userGroups);

                $motdMessages = $this->serverClient->getRequireArray('system_messages', ['message_type' => 'motd']);
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
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];

                $displayName = InputValidation::displayName($request->getPostParameter('displayName'));
                $profileId = InputValidation::profileId($request->getPostParameter('profileId'));

                $profileList = $this->serverClient->getRequireArray('profile_list');
                $userGroups = $this->cachedUserGroups($userInfo->id());
                $visibleProfileList = self::getProfileList($profileList, $userGroups);

                // make sure the profileId is in the list of allowed profiles for this
                // user, it would not result in the ability to use the VPN, but
                // better prevent it early
                if (!\in_array($profileId, array_keys($profileList), true)) {
                    throw new HttpException('no permission to download a configuration for this profile', 400);
                }

                $motdMessages = $this->serverClient->getRequireArray('system_messages', ['message_type' => 'motd']);
                if (0 === \count($motdMessages)) {
                    $motdMessage = false;
                } else {
                    $motdMessage = $motdMessages[0];
                }

                if ($profileList[$profileId]['twoFactor']) {
                    $hasTotpSecret = $this->serverClient->getRequireBool('has_totp_secret', ['user_id' => $userInfo->id()]);
                    $hasYubiKeyId = $this->serverClient->getRequireBool('has_yubi_key_id', ['user_id' => $userInfo->id()]);
                    if (!$hasTotpSecret && !$hasYubiKeyId) {
                        return new HtmlResponse(
                            $this->tpl->render(
                                'vpnPortalNew',
                                [
                                    'profileId' => $profileId,
                                    'errorCode' => 'otpRequired',
                                    'profileList' => $visibleProfileList,
                                    'motdMessage' => $motdMessage,
                                ]
                            )
                        );
                    }
                }
                $expiresAt = date_add(clone $userInfo->authTime(), $this->sessionExpiry);

                return $this->getConfig($request->getServerName(), $profileId, $userInfo->id(), $displayName, $expiresAt);
            }
        );

        $service->get(
            '/certificates',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalCertificates',
                        [
                            'userCertificateList' => $this->serverClient->getRequireArray('client_certificate_list', ['user_id' => $userInfo->id()]),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/deleteCertificate',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $commonName = InputValidation::commonName($request->getPostParameter('commonName'));
                $certInfo = $this->serverClient->getRequireArray('client_certificate_info', ['common_name' => $commonName]);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalConfirmDelete',
                        [
                            'commonName' => $commonName,
                            'displayName' => $certInfo['display_name'],
                        ]
                    )
                );
            }
        );

        $service->post(
            '/deleteCertificateConfirm',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $commonName = InputValidation::commonName($request->getPostParameter('commonName'));

                // no need to validate as we do strict string compare below
                $confirmDelete = $request->getPostParameter('confirmDelete');

                if ('yes' === $confirmDelete) {
                    $this->serverClient->post('delete_client_certificate', ['common_name' => $commonName]);
                    $this->serverClient->post('kill_client', ['common_name' => $commonName]);
                }

                return new RedirectResponse($request->getRootUri().'certificates', 302);
            }
        );

        $service->get(
            '/events',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];

                $userMessages = $this->serverClient->getRequireArray('user_messages', ['user_id' => $userInfo->id()]);

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
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];

                $hasTotpSecret = $this->serverClient->getRequireBool('has_totp_secret', ['user_id' => $userInfo->id()]);
                $yubiKeyId = $this->serverClient->getRequireBool('has_yubi_key_id', ['user_id' => $userInfo->id()]);

                $profileList = $this->serverClient->getRequireArray('profile_list');
                $userGroups = $this->cachedUserGroups($userInfo->id());
                $visibleProfileList = self::getProfileList($profileList, $userGroups);

                $authorizedClients = $this->storage->getAuthorizations($userInfo->id());
                foreach ($authorizedClients as $k => $v) {
                    // false means no longer registered
                    $displayName = false;
                    if (false !== $clientInfo = \call_user_func($this->getClientInfo, $v['client_id'])) {
                        // client_id as name in case no 'display_name' is provided
                        $displayName = $v['client_id'];
                        if (null !== $clientInfo->getDisplayName()) {
                            $displayName = $clientInfo->getDisplayName();
                        }
                    }
                    $authorizedClients[$k]['display_name'] = $displayName;
                }

                $twoFactorEnabledProfiles = [];
                foreach ($visibleProfileList as $profileId => $profileData) {
                    if ($profileData['twoFactor']) {
                        // XXX we have to make sure displayName is always set...
                        $twoFactorEnabledProfiles[] = $profileData['displayName'];
                    }
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalAccount',
                        [
                            'twoFactorEnabledProfiles' => $twoFactorEnabledProfiles,
                            'yubiKeyId' => $yubiKeyId,
                            'hasTotpSecret' => $hasTotpSecret,
                            'userInfo' => $userInfo,
                            'userGroups' => $userGroups,
                            'authorizedClients' => $authorizedClients,
                            'twoFactorMethods' => $this->config->optionalItem('twoFactorMethods', ['totp', 'yubi']),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/removeClientAuthorization',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];
                $clientId = InputValidation::clientId($request->getPostParameter('client_id'));
                $scope = self::validateScope($request->getPostParameter('scope'));

                $this->storage->deleteAuthorization($userInfo->id(), $clientId, $scope);

                // get a list of connections for this user_id with the
                // particular client_id
                // NOTE: we have to get the list first before deleting the
                // certificates, otherwise the clients no longer show up the
                // list... this is NOT good, possible race condition...
                $clientConnections = $this->serverClient->getRequireArray('client_connections', ['client_id' => $clientId, 'user_id' => $userInfo->id()]);

                // delete the certificates from the server
                $this->serverClient->post('delete_client_certificates_of_client_id', ['user_id' => $userInfo->id(), 'client_id' => $clientId]);

                // kill the connections
                foreach ($clientConnections as $profile) {
                    foreach ($profile['connections'] as $connection) {
                        $this->serverClient->post('kill_client', ['common_name' => $connection['common_name']]);
                    }
                }

                return new RedirectResponse($request->getRootUri().'account', 302);
            }
        );

        $service->get(
            '/documentation',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function () {
                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalDocumentation',
                        [
                            'twoFactorMethods' => $this->config->optionalItem('twoFactorMethods', ['totp', 'yubi']),
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
    public static function isMember(array $aclGroupList, array $userGroups)
    {
        // if any of the groups is part of aclGroupList return true
        foreach ($userGroups as $userGroup) {
            if (\in_array($userGroup, $aclGroupList, true)) {
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
     * @return \SURFnet\VPN\Common\Http\Response
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
        $profileData = $serverProfiles[$profileId];

        // get the CA & tls-auth
        $serverInfo = $this->serverClient->getRequireArray('server_info');

        $clientConfig = ClientConfig::get($profileData, $serverInfo, $clientCertificate, $this->shuffleHosts);

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
     * and that the user is a member of the required groups in case ACLs are
     * enabled.
     *
     * @return array
     */
    private static function getProfileList(array $serverProfiles, array $userGroups)
    {
        $profileList = [];
        foreach ($serverProfiles as $profileId => $profileData) {
            if ($profileData['hideProfile']) {
                continue;
            }
            if ($profileData['enableAcl']) {
                // is the user member of the aclGroupList?
                if (!self::isMember($profileData['aclGroupList'], $userGroups)) {
                    continue;
                }
            }

            $profileList[$profileId] = [
                'displayName' => $profileData['displayName'],
                'twoFactor' => $profileData['twoFactor'],
            ];
        }

        return $profileList;
    }

    /**
     * @param string $userId
     *
     * @return array
     */
    private function cachedUserGroups($userId)
    {
        if ($this->session->has('_cached_groups_user_id')) {
            // does it match the current userId?
            if ($userId === $this->session->get('_cached_groups_user_id')) {
                $cachedGroups = $this->session->get('_cached_groups');
                // support old format with id/displayName keys of simple array<string>
                if (0 === \count($cachedGroups) || \is_string($cachedGroups[0])) {
                    // no entries, or already new format
                    return $cachedGroups;
                }

                $this->session->set('_cached_groups', $this->serverClient->getRequireArray('user_groups', ['user_id' => $userId]));

                return $this->session->get('_cached_groups');
            }
        }

        $this->session->set('_cached_groups_user_id', $userId);
        $this->session->set('_cached_groups', $this->serverClient->getRequireArray('user_groups', ['user_id' => $userId]));

        return $this->session->get('_cached_groups');
    }
}
