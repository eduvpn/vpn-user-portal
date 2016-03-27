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
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Tpl\TemplateManagerInterface;

class VpnApiModule implements ServiceModuleInterface
{
    /** @var \fkooman\Tpl\TemplateManagerInterface */
    private $templateManager;

    /** @var VpnConfigApiClient */
    private $vpnConfigApiClient;

    public function __construct(TemplateManagerInterface $templateManager, VpnConfigApiClient $vpnConfigApiClient)
    {
        $this->templateManager = $templateManager;
        $this->vpnConfigApiClient = $vpnConfigApiClient;
    }

    public function init(Service $service)
    {
        // Add a configuration
        $service->post(
            '/api/config',
            function (Request $request, UserInfoInterface $userInfo) {
                $configName = $request->getPostParameter('name');
                Utils::validateConfigName($configName);

                return $this->addConfig($userInfo, $configName);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('api'),
                ),
            )
        );
    }

    private function addConfig(UserInfoInterface $userInfo, $configName)
    {
        $configData = $this->vpnConfigApiClient->addConfiguration($userInfo->getUserId(), $configName);
        $configFile = $this->templateManager->render(
            'client',
            $configData['certificate']
        );
        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setBody($configFile);

        return $response;
    }
}
