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
use SURFnet\VPN\Common\HttpClient\CaClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Http\Response;

class VpnPortalModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\TplInterface */
    private $tpl;

    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \SURFnet\VPN\Common\HttpClient\CaClient */
    private $caClient;

    /** @var \SURFnet\VPN\Common\Http\SessionInterface */
    private $session;

    public function __construct(TplInterface $tpl, ServerClient $serverClient, CaClient $caClient, SessionInterface $session)
    {
        $this->tpl = $tpl;
        $this->serverClient = $serverClient;
        $this->caClient = $caClient;
        $this->session = $session;
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

                $serverPools = $this->serverClient->serverPools();
                $userGroups = $this->serverClient->userGroups($userId);
                $poolList = self::getAuthorizedPoolList($serverPools, $userGroups);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalNew',
                        [
                            'poolList' => $poolList,
                            'maxNameLength' => 63 - mb_strlen($userId),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/new',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $configName = $request->getPostParameter('configName');
                InputValidation::configName($configName);
                $poolId = $request->getPostParameter('poolId');
                InputValidation::poolId($poolId);

                // the CN, built of userId + '_' + configName cannot exceed
                // a length of 64 as the CN cert is only allowed to be of
                // length 64
                $cnLength = mb_strlen($userId) + mb_strlen($configName) + 1;
                if (64 < $cnLength) {
                    throw new HttpException(
                        sprintf('configName too long, limited to "%d" characters', 63 - mb_strlen($userId)),
                        400
                    );
                }

                $serverPools = $this->serverClient->serverPools();
                $userGroups = $this->serverClient->userGroups($userId);
                $poolList = self::getAuthorizedPoolList($serverPools, $userGroups);

                // make sure the poolId is in the list of allowed pools for this
                // user, it would not result in the ability to use the VPN, but
                // better prevent it early
                if (!in_array($poolId, array_keys($poolList))) {
                    throw new HttpException('no permission to create a configuration for this poolId', 400);
                }

                // check that a certificate does not yet exist with this configName
                $userCertificateList = $this->caClient->userCertificateList($userId);
                foreach ($userCertificateList as $userCertificate) {
                    if ($configName === $userCertificate['name']) {
                        return new HtmlResponse(
                            $this->tpl->render(
                                'vpnPortalNew',
                                [
                                    'poolId' => $poolId,
                                    'errorCode' => 'nameAlreadyUsed',
                                    'poolList' => $poolList,
                                    'configName' => $configName,
                                    'maxNameLength' => 63 - mb_strlen($userId),
                                ]
                            )
                        );
                    }
                }

                if ($poolList[$poolId]['twoFactor']) {
                    $hasOtpSecret = $this->serverClient->hasOtpSecret($userId);
                    if (!$hasOtpSecret) {
                        return new HtmlResponse(
                            $this->tpl->render(
                                'vpnPortalNew',
                                [
                                    'poolId' => $poolId,
                                    'errorCode' => 'otpRequired',
                                    'poolList' => $poolList,
                                    'maxNameLength' => 63 - mb_strlen($userId),
                                ]
                            )
                        );
                    }
                }

                return $this->getConfig($request->getServerName(), $poolId, $userId, $configName);
            }
        );

        $service->get(
            '/configurations',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $userCertificateList = $this->caClient->userCertificateList($userId);

                // XXX we need a call to retrieve the disabled common names
                // for a particular user, not for all, that seems overkill
                $disabledCommonNames = $this->serverClient->disabledCommonNames();

                // check all valid certificates to see if they are disabled
                foreach ($userCertificateList as $i => $userCertificate) {
                    if ('V' === $userCertificate['state']) {
                        $commonName = sprintf('%s_%s', $userCertificate['user_id'], $userCertificate['name']);
                        if (in_array($commonName, $disabledCommonNames)) {
                            $userCertificateList[$i]['state'] = 'D';
                        }
                    }
                }

                // XXX we probably should support sorting of the certificate list
                // and/or choose a default sorting that makes sense

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalConfigurations',
                        [
                            'userCertificateList' => $userCertificateList,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/disableCertificate',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $configName = $request->getPostParameter('configName');
                InputValidation::configName($configName);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalConfirmDisable',
                        [
                            'configName' => $configName,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/disableCertificateConfirm',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $configName = $request->getPostParameter('configName');
                InputValidation::configName($configName);
                $confirmDisable = $request->getPostParameter('confirmDisable');
                InputValidation::confirmDisable($confirmDisable);

                if ('yes' === $confirmDisable) {
                    $this->serverClient->disableCommonName(sprintf('%s_%s', $userId, $configName));
                    $this->serverClient->killClient(sprintf('%s_%s', $userId, $configName));
                }

                return new RedirectResponse($request->getRootUri().'configurations', 302);
            }
        );

        $service->get(
            '/account',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $hasOtpSecret = $this->serverClient->hasOtpSecret($userId);
                $userGroups = $this->serverClient->userGroups($userId);
                $serverPools = $this->serverClient->serverPools();

                $otpEnabledPools = [];
                foreach ($serverPools as $poolData) {
                    if ($poolData['enableAcl']) {
                        // is the user member of the aclGroupList?
                        if (!self::isMember($userGroups, $poolData['aclGroupList'])) {
                            continue;
                        }
                    }

                    if ($poolData['twoFactor']) {
                        // XXX we have to make sure displayName is always set...
                        $otpEnabledPools[] = $poolData['displayName'];
                    }
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalAccount',
                        [
                            'otpEnabledPools' => $otpEnabledPools,
                            'hasOtpSecret' => $hasOtpSecret,
                            'userId' => $userId,
                            'userGroups' => $userGroups,
                        ]
                    )
                );
            }
        );

        $service->get(
            '/documentation',
            function () {
                return new HtmlResponse($this->tpl->render('vpnPortalDocumentation', []));
            }
        );
    }

    private function getConfig($serverName, $poolId, $userId, $configName)
    {
        // create a certificate
        $clientCertificate = $this->caClient->addClientCertificate($userId, $configName);

        // obtain information about this pool to be able to construct
        // a client configuration file
        $poolData = $this->serverClient->serverPool($poolId);

        $clientConfig = ClientConfig::get($poolData, $clientCertificate, false);

        // XXX consider the timezone in the data call, this will be weird
        // when not using same timezone as user machine...
        $clientConfigFile = sprintf('%s_%s_%s', $serverName, date('Ymd'), $configName);

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
     * Filter the list of pools by checking if the user is a member of the
     * required groups in case ACLs are enabled.
     */
    private static function getAuthorizedPoolList(array $serverPools, array $userGroups)
    {
        $poolList = [];
        foreach ($serverPools as $poolId => $poolData) {
            if ($poolData['enableAcl']) {
                // is the user member of the aclGroupList?
                if (!self::isMember($userGroups, $poolData['aclGroupList'])) {
                    continue;
                }
            }

            $poolList[$poolId] = [
                'displayName' => $poolData['displayName'],
                'twoFactor' => $poolData['twoFactor'],
            ];
        }

        return $poolList;
    }
}
