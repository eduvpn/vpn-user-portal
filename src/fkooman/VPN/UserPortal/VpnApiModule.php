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

use fkooman\Http\RedirectResponse;
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

    /** @var ApiDb */
    private $apiDb;

    /** @var VpnConfigApiClient */
    private $vpnConfigApiClient;

    public function __construct(TemplateManagerInterface $templateManager, ApiDb $apiDb, VpnConfigApiClient $vpnConfigApiClient)
    {
        $this->templateManager = $templateManager;
        $this->apiDb = $apiDb;
        $this->vpnConfigApiClient = $vpnConfigApiClient;
    }

    public function init(Service $service)
    {
        $userAuth = array(
            'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                'activate' => array('user'),
            ),
        );

        $apiAuth = array(
            'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                'activate' => array('api'),
            ),
        );

        $service->get(
            '/api',
            function (Request $request, UserInfoInterface $userInfo) {
                return $this->getApiPage($userInfo);
            },
            $userAuth
        );

        $service->post(
            '/api',
            function (Request $request, UserInfoInterface $userInfo) {
                return $this->addKey($request, $userInfo);
            },
            $userAuth
        );

        $service->delete(
            '/api',
            function (Request $request, UserInfoInterface $userInfo) {
                $this->deleteKey($userInfo);

                return new RedirectResponse($request->getUrl()->getRootUrl().'api');
            },
            $userAuth
        );

        // Add a configuration
        $service->post(
            '/api/config',
            function (Request $request, UserInfoInterface $userInfo) {
                $configName = $request->getPostParameter('name');
                Utils::validateConfigName($configName);

                return $this->addConfig($userInfo, $configName);
            },
            $apiAuth
        );
    }

    private function getApiPage(UserInfoInterface $userInfo, $userPass = null)
    {
        $result = $this->apiDb->getUserNameForUserId($userInfo->getUserId());

        return $this->templateManager->render(
            'vpnPortalApi',
            array(
                'userName' => $result['user_name'],
                'userPass' => $userPass,
            )
        );
    }

    private function addKey(Request $request, UserInfoInterface $userInfo)
    {
        $userName = bin2hex(random_bytes(8));
        $userPass = bin2hex(random_bytes(8));
        $userPassHash = password_hash($userPass, PASSWORD_DEFAULT);
        $this->apiDb->addKey($userInfo->getUserId(), $userName, $userPassHash);

        return $this->getApiPage($userInfo, $userPass);
    }

    private function deleteKey(UserInfoInterface $userInfo)
    {
        $this->apiDb->deleteKey($userInfo->getUserId());
    }

    private function addConfig(UserInfoInterface $userInfo, $configName)
    {
        // XXX check if config exists first
        $result = $this->apiDb->getUserIdForUserName($userInfo->getUserId());

        $configData = $this->vpnConfigApiClient->addConfiguration($result['user_id'], $configName);
        $configFile = $this->templateManager->render(
            'client',
            $configData['certificate']
        );
        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setBody($configFile);

        return $response;
    }
}
