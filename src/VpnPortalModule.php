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

use SURFnet\VPN\Common\Http\SessionInterface;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\HtmlResponse;
use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\Http\Exception\HttpException;
use SURFnet\VPN\Common\TplInterface;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Http\Response;
use SURFnet\VPN\Portal\OAuth\TokenStorage;

class VpnPortalModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\TplInterface */
    private $tpl;

    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \SURFnet\VPN\Common\Http\SessionInterface */
    private $session;

    /** @var \SURFnet\VPN\Portal\OAuth\TokenStorage */
    private $tokenStorage;

    /** @var bool */
    private $shuffleHosts;

    public function __construct(TplInterface $tpl, ServerClient $serverClient, SessionInterface $session, TokenStorage $tokenStorage)
    {
        $this->tpl = $tpl;
        $this->serverClient = $serverClient;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
        $this->shuffleHosts = true;
    }

    public function setShuffleHosts($shuffleHosts)
    {
        $this->shuffleHosts = (bool) $shuffleHosts;
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

                $profileList = $this->serverClient->profileList();
                $userGroups = $this->cachedUserGroups($userId);
                $visibleProfileList = self::getProfileList($profileList, $userGroups);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalNew',
                        [
                            'profileList' => $visibleProfileList,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/new',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $displayName = $request->getPostParameter('displayName');
                InputValidation::displayName($displayName);
                $profileId = $request->getPostParameter('profileId');
                InputValidation::profileId($profileId);

                $profileList = $this->serverClient->profileList();
                $userGroups = $this->cachedUserGroups($userId);
                $visibleProfileList = self::getProfileList($profileList, $userGroups);

                // make sure the profileId is in the list of allowed profiles for this
                // user, it would not result in the ability to use the VPN, but
                // better prevent it early
                if (!in_array($profileId, array_keys($profileList))) {
                    throw new HttpException('no permission to create a configuration for this profileId', 400);
                }

                if ($profileList[$profileId]['twoFactor']) {
                    $hasOtpSecret = $this->serverClient->hasTotpSecret($userId);
                    if (!$hasOtpSecret) {
                        return new HtmlResponse(
                            $this->tpl->render(
                                'vpnPortalNew',
                                [
                                    'profileId' => $profileId,
                                    'errorCode' => 'otpRequired',
                                    'profileList' => $visibleProfileList,
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
                            'userCertificateList' => $this->serverClient->listClientCertificates($userId),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/disableCertificate',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $commonName = $request->getPostParameter('commonName');
                InputValidation::commonName($commonName);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalConfirmDisable',
                        [
                            'commonName' => $commonName,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/disableCertificateConfirm',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $commonName = $request->getPostParameter('commonName');
                InputValidation::commonName($commonName);
                $confirmDisable = $request->getPostParameter('confirmDisable');
                InputValidation::confirmDisable($confirmDisable);

                if ('yes' === $confirmDisable) {
                    $this->serverClient->disableClientCertificate($commonName);
                    $this->serverClient->killClient($commonName);
                }

                return new RedirectResponse($request->getRootUri().'configurations', 302);
            }
        );

        $service->get(
            '/account',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $hasOtpSecret = $this->serverClient->hasTotpSecret($userId);

                $profileList = $this->serverClient->profileList();
                $userGroups = $this->cachedUserGroups($userId);
                $visibleProfileList = self::getProfileList($profileList, $userGroups);

                $authorizedClients = $this->tokenStorage->getAuthorizedClients($userId);

                $otpEnabledProfiles = [];
                foreach ($visibleProfileList as $profileId => $profileData) {
                    if ($profileData['twoFactor']) {
                        // XXX we have to make sure displayName is always set...
                        $otpEnabledProfiles[] = $profileData['displayName'];
                    }
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalAccount',
                        [
                            'otpEnabledProfiles' => $otpEnabledProfiles,
                            'hasOtpSecret' => $hasOtpSecret,
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
                $clientId = $request->getPostParameter('client_id');
                InputValidation::clientId($clientId);

                $this->tokenStorage->removeClientTokens($userId, $clientId);

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

    private function getConfig($serverName, $profileId, $userId, $displayName)
    {
        // create a certificate
        $clientCertificate = $this->serverClient->addClientCertificate($userId, $displayName);

        $serverProfiles = $this->serverClient->profileList();
        $profileData = $serverProfiles[$profileId];

        $clientConfig = ClientConfig::get($profileData, $clientCertificate, $this->shuffleHosts);

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
        $this->session->set('_cached_groups', $this->serverClient->userGroups($userId));

        return $this->session->get('_cached_groups');
    }
}
