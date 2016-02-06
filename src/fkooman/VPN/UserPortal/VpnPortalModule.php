<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace fkooman\VPN\UserPortal;

use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Http\RedirectResponse;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;
use fkooman\Tpl\TemplateManagerInterface;

class VpnPortalModule implements ServiceModuleInterface
{
    /** @var \fkooman\Tpl\TemplateManagerInterface */
    private $templateManager;

    /** @var VpnConfigApiClient */
    private $vpnConfigApiClient;

    /** @var VpnServerApiClient */
    private $vpnServerApiClient;

    public function __construct(TemplateManagerInterface $templateManager, VpnConfigApiClient $vpnConfigApiClient, VpnServerApiClient $vpnServerApiClient)
    {
        $this->templateManager = $templateManager;
        $this->vpnConfigApiClient = $vpnConfigApiClient;
        $this->vpnServerApiClient = $vpnServerApiClient;
    }

    public function init(Service $service)
    {
        /* REDIRECTS **/
        $service->get(
            '/config/',
            function (Request $request) {
                return new RedirectResponse($request->getUrl()->getRootUrl(), 301);
            }
        );

        $service->get(
            '/',
            function (Request $request, UserInfoInterface $u) {
                return new RedirectResponse($request->getUrl()->getRootUrl().'new', 302);
            }
        );

        $service->get(
            '/new',
            function (Request $request, UserInfoInterface $u) {
                return $this->templateManager->render(
                    'vpnPortalNew',
                    array(
                        'advanced' => (bool) $request->getUrl()->getQueryParameter('advanced'),
                        'cnLength' => 63 - strlen($u->getUserId()),
                    )
                );
            }
        );

        $service->post(
            '/new',
            function (Request $request, UserInfoInterface $u) {
                $configName = $request->getPostParameter('name');
                $optionZip = (bool) $request->getPostParameter('option_zip');

                return $service->getConfig($u->getUserId(), $configName, $optionZip);
            }
        );

        $service->get(
            '/configurations',
            function (Request $request, UserInfoInterface $u) {
                $certList = $this->vpnConfigApiClient->getCertList($u->getUserId());
                $disabledCommonNames = $this->vpnServerApiClient->getCcdDisable($u->getUserId());

                $activeVpnConfigurations = array();
                $revokedVpnConfigurations = array();
                $disabledVpnConfigurations = array();
                $expiredVpnConfigurations = array();

                foreach ($certList['items'] as $c) {
                    if ('E' === $c['state']) {
                        $expiredVpnConfigurations[] = $c;
                    } elseif ('R' === $c['state']) {
                        $revokedVpnConfigurations[] = $c;
                    } elseif ('V' === $c['state']) {
                        $commonName = $u->getUserId().'_'.$c['name'];
                        if (in_array($commonName, $disabledCommonNames['disabled'])) {
                            $disabledVpnConfigurations[] = $c;
                        } else {
                            $activeVpnConfigurations[] = $c;
                        }
                    }
                }

                return $this->templateManager->render(
                    'vpnPortalConfigurations',
                    array(
                        'activeVpnConfigurations' => $activeVpnConfigurations,
                        'disabledVpnConfigurations' => $disabledVpnConfigurations,
                        'revokedVpnConfigurations' => $revokedVpnConfigurations,
                        'expiredVpnConfigurations' => $expiredVpnConfigurations,
                    )
                );
            }
        );

        $service->post(
            '/revoke',
            function (Request $request, UserInfoInterface $u) {
                $configName = $request->getPostParameter('name');
                $formConfirm = $request->getPostParameter('confirm');

                if (is_null($formConfirm)) {
                    // ask for confirmation
                    return $this->templateManager->render(
                        'vpnPortalConfirmRevoke',
                        array(
                            'configName' => $configName,
                        )
                    );
                }

                if ('yes' === $formConfirm) {
                    // user said yes
                    $this->revokeConfig($u->getUserId(), $configName);
                }

                return new RedirectResponse($request->getUrl()->getRootUrl().'configurations', 302);
            }
        );

        $service->get(
            '/whoami',
            function (Request $request, UserInfoInterface $u) {
                $response = new Response(200, 'text/plain');
                $response->setBody($u->getUserId());

                return $response;
            }
        );

        $service->get(
            '/documentation',
            function (Request $request, UserInfoInterface $u) {
                return $this->templateManager->render(
                    'vpnPortalDocumentation',
                    array(
                    )
                );
            }
        );
    }

    public function getConfig($userId, $configName, $returnZip = true)
    {
        Utils::validateConfigName($configName);

        // userId + configName length cannot be longer than 64 as the
        // certificate CN cannot be longer than 64
        if (64 < strlen($userId) + strlen($configName) + 1) {
            throw new BadRequestException(
                sprintf('commonName length MUST not exceed %d', 63 - strlen($userId))
            );
        }

        // make sure the configuration does not exist yet
        // XXX: this should be optimized a bit...
        $certList = $this->vpnConfigApiClient->getCertList($userId);
        foreach ($certList['items'] as $cert) {
            if ($configName === $cert['name']) {
                return $this->templateManager->render(
                    'vpnPortalErrorConfigExists',
                    array(
                        'configName' => $configName,
                    )
                );
            }
        }

        # get config from API
        $configData = $this->vpnConfigApiClient->addConfiguration($userId, $configName);

        if ($returnZip) {
            // return Zipped OpenVPN config file with separate certificates
            $configData = Utils::configToZip($configName, $configData);
            $response = new Response(200, 'application/zip');
            $response->setHeader('Content-Disposition', sprintf('attachment; filename="%s.zip"', $configName));
            $response->setBody($configData);
        } else {
            // return OpenVPN config file
            $response = new Response(200, 'application/x-openvpn-profile');
            $response->setHeader('Content-Disposition', sprintf('attachment; filename="%s.ovpn"', $configName));
            $response->setBody($configData);
        }

        return $response;
    }

    public function revokeConfig($userId, $configName)
    {
        Utils::validateConfigName($configName);

        $this->vpnConfigApiClient->revokeConfiguration($userId, $configName);

        // trigger a CRL reload in the servers
        $this->vpnServerApiClient->postCrlFetch();
    }
}
