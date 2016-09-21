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
                $hasOtpSecret = $this->serverClient->hasOtpSecret($userId);
                $userGroups = $this->serverClient->userGroups($userId);

                $poolList = [];
                $otpEnabledPools = [];

                foreach ($serverPools as $poolId => $poolData) {
                    if ($poolData['enableAcl']) {
                        // is the user member of the aclGroupList?
                        if (!self::isMember($userGroups, $poolData['aclGroupList'])) {
                            continue;
                        }
                    }

                    // any of them requires 2FA and we are not enrolled?
                    if (!$hasOtpSecret) {
                        if ($poolData['twoFactor']) {
                            $otpEnabledPools[] = $poolData['displayName'];
                        }
                    }

                    $poolList[] = [
                        'poolId' => $poolId,
                        'displayName' => $poolData['displayName'],
                    ];
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalNew',
                        [
                            'poolList' => $poolList,
                            'otpEnabledPools' => $otpEnabledPools,
                            'maxNameLength' => 63 - strlen($userId),
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

                return $this->getConfig($userId, $configName, $poolId);
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

        $service->post(
            '/setLanguage',
            function (Request $request) {
                $setLanguage = $request->getPostParameter('setLanguage');
                InputValidation::setLanguage($setLanguage);

                $this->session->set('activeLanguage', $setLanguage);

                return new RedirectResponse($request->getHeader('HTTP_REFERER'), 302);
            }
        );

        $service->get(
            '/documentation',
            function () {
                return new HtmlResponse($this->tpl->render('vpnPortalDocumentation', []));
            }
        );
    }

    private function getConfig($userId, $configName, $poolId)
    {
        // XXX dragons ahead!
        // XXX why does this even work?!

        // userId + configName length cannot be longer than 64 as the
        // certificate CN cannot be longer than 64
        if (64 < strlen($userId) + strlen($configName) + 1) {
            throw new HttpException(
                sprintf('commonName length MUST not exceed %d', 63 - strlen($userId)),
                400
            );
        }

        // make sure the configuration does not exist yet
        // XXX: this should be optimized a bit...
        $userCertificateList = $this->caClient->userCertificateList($userId);

        foreach ($userCertificateList as $userCertificate) {
            if ($configName === $userCertificate['name']) {
                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalErrorConfigExists',
                        [
                            'configName' => $configName,
                        ]
                    )
                );
            }
        }

        $certData = $this->caClient->addClientCertificate($userId, $configName);
        $serverPools = $this->serverClient->serverPools();

        $serverPool = null;
        foreach ($serverPools as $i => $pool) {
            if ($poolId === $i) {
                $serverPool = $pool;
            }
        }
        if (is_null($serverPool)) {
            throw new HttpException('chosen pool does not exist', 400);
        }

        // XXX if 2FA is required, we should warn the user to first enroll!

        $remoteEntities = [];
        $processCount = $serverPool['processCount'];

        // XXX fix this stuff, this should really not be here...
        for ($i = 0; $i < $processCount; ++$i) {
            if (1 === $processCount || $i !== $processCount - 1) {
                $proto = 'udp';
                $port = 1194 + $i;
            } else {
                $proto = 'tcp';
                $port = 443;
            }

            $remoteEntities[] = [
                'port' => $port,
                'proto' => $proto,
                'host' => $serverPool['hostName'],
            ];
        }

        $remoteEntities = ['remote' => $remoteEntities];

        $clientConfig = new ClientConfig();
        $vpnConfig = implode(
            PHP_EOL,
            $clientConfig->get(
                array_merge(['twoFactor' => $serverPool['twoFactor']], $certData, $remoteEntities),
                false  // no randomizing
            )
        );

        // XXX get this from the server info, not from the current request, this is silly ;)

        $httpHost = 'FIXME';
        if (false !== strpos($httpHost, ':')) {
            // strip port
            $httpHost = substr($httpHost, 0, strpos($httpHost, ':'));
        }

        $configFileName = sprintf('%s_%s_%s', $httpHost, date('Ymd'), $configName);

        // return an OVPN file
        $response = new Response(200, 'application/x-openvpn-profile');
        $response->addHeader('Content-Disposition', sprintf('attachment; filename="%s.ovpn"', $configFileName));
        $response->setBody($vpnConfig);

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
}
