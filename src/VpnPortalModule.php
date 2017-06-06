<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Server\Storage;
use fkooman\SeCookie\SessionInterface;
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
    /** @var \SURFnet\VPN\Common\TplInterface */
    private $tpl;

    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \fkooman\OAuth\Server\Storage */
    private $storage;

    /** @var callable */
    private $getClientInfo;

    /** @var bool */
    private $shuffleHosts = true;

    /** @var array */
    private $addVpnProtoPorts = [];

    public function __construct(TplInterface $tpl, ServerClient $serverClient, SessionInterface $session, Storage $storage, callable $getClientInfo)
    {
        $this->tpl = $tpl;
        $this->serverClient = $serverClient;
        $this->session = $session;
        $this->storage = $storage;
        $this->getClientInfo = $getClientInfo;
    }

    public function setShuffleHosts($shuffleHosts)
    {
        $this->shuffleHosts = (bool) $shuffleHosts;
    }

    public function setAddVpnProtoPorts(array $addVpnProtoPorts)
    {
        $this->addVpnProtoPorts = $addVpnProtoPorts;
    }

    public function init(Service $service)
    {
        $service->get(
            '/',
            function (Request $request) {
                return new RedirectResponse($request->getRootUri().'new', 302);
            }
        );

        $service->get(
            '/new',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $profileList = $this->serverClient->get('profile_list');
                $userGroups = $this->cachedUserGroups($userId);
                $visibleProfileList = self::getProfileList($profileList, $userGroups);

                $motdMessages = $this->serverClient->get('system_messages', ['message_type' => 'motd']);
                if (0 === count($motdMessages)) {
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
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $displayName = InputValidation::displayName($request->getPostParameter('displayName'));
                $profileId = InputValidation::profileId($request->getPostParameter('profileId'));

                $profileList = $this->serverClient->get('profile_list');
                $userGroups = $this->cachedUserGroups($userId);
                $visibleProfileList = self::getProfileList($profileList, $userGroups);

                // make sure the profileId is in the list of allowed profiles for this
                // user, it would not result in the ability to use the VPN, but
                // better prevent it early
                if (!in_array($profileId, array_keys($profileList))) {
                    throw new HttpException('no permission to create a configuration for this profileId', 400);
                }

                $motdMessages = $this->serverClient->get('system_messages', ['message_type' => 'motd']);
                if (0 === count($motdMessages)) {
                    $motdMessage = false;
                } else {
                    $motdMessage = $motdMessages[0];
                }

                if ($profileList[$profileId]['twoFactor']) {
                    $hasTotpSecret = $this->serverClient->get('has_totp_secret', ['user_id' => $userId]);
                    $hasYubiKeyId = $this->serverClient->get('has_yubi_key_id', ['user_id' => $userId]);
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

                return $this->getConfig($request->getServerName(), $profileId, $userId, $displayName);
            }
        );

        $service->get(
            '/configurations',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalConfigurations',
                        [
                            'userCertificateList' => $this->serverClient->get('client_certificate_list', ['user_id' => $userId]),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/deleteCertificate',
            function (Request $request, array $hookData) {
                $commonName = InputValidation::commonName($request->getPostParameter('commonName'));
                $certInfo = $this->serverClient->get('client_certificate_info', ['common_name' => $commonName]);

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
            function (Request $request, array $hookData) {
                $commonName = InputValidation::commonName($request->getPostParameter('commonName'));

                // no need to validate as we do strict string compare below
                $confirmDelete = $request->getPostParameter('confirmDelete');

                if ('yes' === $confirmDelete) {
                    $this->serverClient->post('delete_client_certificate', ['common_name' => $commonName]);
                    $this->serverClient->post('kill_client', ['common_name' => $commonName]);
                }

                return new RedirectResponse($request->getRootUri().'configurations', 302);
            }
        );

        $service->get(
            '/events',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $userMessages = $this->serverClient->get('user_messages', ['user_id' => $userId]);

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
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $hasTotpSecret = $this->serverClient->get('has_totp_secret', ['user_id' => $userId]);
                $yubiKeyId = $this->serverClient->get('yubi_key_id', ['user_id' => $userId]);

                $profileList = $this->serverClient->get('profile_list');
                $userGroups = $this->cachedUserGroups($userId);
                $visibleProfileList = self::getProfileList($profileList, $userGroups);

                $authorizedClients = $this->storage->getAuthorizations($userId);
                foreach ($authorizedClients as $k => $v) {
                    // false means no longer registered
                    $displayName = false;
                    if (false !== $clientInfo = call_user_func($this->getClientInfo, $v['client_id'])) {
                        // client_id as name in case no 'display_name' is provided
                        $displayName = $v['client_id'];
                        if (array_key_exists('display_name', $clientInfo)) {
                            $displayName = $clientInfo['display_name'];
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
                            'userId' => $userId,
                            'userGroups' => $userGroups,
                            'authorizedClients' => $authorizedClients,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/removeClientAuthorization',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];
                $clientId = InputValidation::clientId($request->getPostParameter('client_id'));
                $scope = self::validateScope($request->getPostParameter('scope'));

                $this->storage->deleteAuthorization($userId, $clientId, $scope);

                return new RedirectResponse($request->getRootUri().'account', 302);
            }
        );

        $service->get(
            '/documentation',
            function () {
                return new HtmlResponse($this->tpl->render('vpnPortalDocumentation', []));
            }
        );
    }

    /**
     * @param string $scope
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

    private function getConfig($serverName, $profileId, $userId, $displayName)
    {
        // create a certificate
        $clientCertificate = $this->serverClient->post('add_client_certificate', ['user_id' => $userId, 'display_name' => $displayName]);

        $serverProfiles = $this->serverClient->get('profile_list');
        $profileData = $serverProfiles[$profileId];

        // get the CA & tls-auth
        $serverInfo = $this->serverClient->get('server_info');

        $clientConfig = ClientConfig::get($profileData, $serverInfo, $clientCertificate, $this->shuffleHosts, $this->addVpnProtoPorts);

        // convert the OpenVPN file to "Windows" format, no platform cares, but
        // in Notepad on Windows it looks not so great everything on one line
        $clientConfig = str_replace("\n", "\r\n", $clientConfig);

        // XXX consider the timezone in the data call, this will be weird
        // when not using same timezone as user machine...
        $clientConfigFile = sprintf('%s_%s_%s_%s', $serverName, $profileId, date('Ymd'), $displayName);

        $response = new Response(200, 'application/x-openvpn-profile');
        $response->addHeader('Content-Disposition', sprintf('attachment; filename="%s.ovpn"', $clientConfigFile));
        $response->setBody($clientConfig);

        return $response;
    }

    private static function isMember(array $userGroups, array $aclGroupList)
    {
        // if any of the groups in userGroups is part of aclGroupList return
        // true, otherwise false
        foreach ($userGroups as $userGroup) {
            if (in_array($userGroup['id'], $aclGroupList)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter the list of profiles by checking if the profile should be shown,
     * and that the user is a member of the required groups in case ACLs are
     * enabled.
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
                if (!self::isMember($userGroups, $profileData['aclGroupList'])) {
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

    private function cachedUserGroups($userId)
    {
        if ($this->session->has('_cached_groups_user_id')) {
            // does it match the current userId?
            if ($userId === $this->session->get('_cached_groups_user_id')) {
                return $this->session->get('_cached_groups');
            }
        }

        $this->session->set('_cached_groups_user_id', $userId);
        $this->session->set('_cached_groups', $this->serverClient->get('user_groups', ['user_id' => $userId]));

        return $this->session->get('_cached_groups');
    }
}
