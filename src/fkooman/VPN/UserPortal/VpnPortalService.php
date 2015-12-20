<?php

namespace fkooman\VPN\UserPortal;

use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Http\RedirectResponse;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Http\Exception\NotFoundException;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Rest\Service;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;
use fkooman\Tpl\TemplateManagerInterface;

class VpnPortalService extends Service
{
    /** @var PdoStorage */
    private $db;

    /** @var \fkooman\Tpl\TemplateManagerInterface */
    private $templateManager;

    /** @var VpnConfigApiClient */
    private $VpnConfigApiClient;

    public function __construct(PdoStorage $db, TemplateManagerInterface $templateManager, VpnConfigApiClient $VpnConfigApiClient)
    {
        parent::__construct();

        $this->db = $db;
        $this->templateManager = $templateManager;
        $this->VpnConfigApiClient = $VpnConfigApiClient;

        /* REDIRECTS **/
        $this->get(
            '/config/',
            function (Request $request) {
                return new RedirectResponse($request->getUrl()->getRootUrl(), 301);
            }
        );

        $this->get(
            '/',
            function (Request $request, UserInfoInterface $u) {
                return new RedirectResponse($request->getUrl()->getRootUrl().'new', 302);
            }
        );

        $this->get(
            '/new',
            function (Request $request, UserInfoInterface $u) {
                return $this->templateManager->render(
                    'vpnPortalNew',
                    array(
                        'advanced' => (bool) $request->getUrl()->getQueryParameter('advanced'),
                        'isBlocked' => $this->isBlocked($u->getUserId()),
                    )
                );
            }
        );

        $this->post(
            '/new',
            function (Request $request, UserInfoInterface $u) {
                $configName = $request->getPostParameter('name');
                $optionZip = (bool) $request->getPostParameter('option_zip');

                return $this->getConfig($u->getUserId(), $configName, $optionZip);
            }
        );

        $this->get(
            '/active',
            function (Request $request, UserInfoInterface $u) {
                $vpnConfigurations = $this->db->getConfigurations($u->getUserId(), PdoStorage::STATUS_ACTIVE);

                return $this->templateManager->render(
                    'vpnPortalActive',
                    array(
                        'vpnConfigurations' => $vpnConfigurations,
                        'isBlocked' => $this->isBlocked($u->getUserId()),
                    )
                );
            }
        );

        $this->get(
            '/revoked',
            function (Request $request, UserInfoInterface $u) {
                $vpnConfigurations = $this->db->getConfigurations($u->getUserId(), PdoStorage::STATUS_REVOKED);

                return $this->templateManager->render(
                    'vpnPortalRevoked',
                    array(
                        'vpnConfigurations' => $vpnConfigurations,
                        'isBlocked' => $this->isBlocked($u->getUserId()),
                    )
                );
            }
        );

        $this->post(
            '/revoke',
            function (Request $request, UserInfoInterface $u) {
                $configName = $request->getPostParameter('name');

                $this->revokeConfig($u->getUserId(), $configName);

                return new RedirectResponse($request->getUrl()->getRootUrl().'revoked', 302);
            }
        );

        $this->get(
            '/documentation',
            function (Request $request, UserInfoInterface $u) {
                return $this->templateManager->render(
                    'vpnPortalDocumentation',
                    array(
                        'isBlocked' => $this->isBlocked($u->getUserId()),
                    )
                );
            }
        );
    }

    public function getConfig($userId, $configName, $returnZip = true)
    {
        $this->requireNotBlocked($userId);
        Utils::validateConfigName($configName);

        # make sure config does not exist yet
        if ($this->db->isExistingConfiguration($userId, $configName)) {
            throw new BadRequestException('configuration already exists with this name');
        }

        # add configuration
        $this->db->addConfiguration($userId, $configName);

        # get config from API
        $configData = $this->VpnConfigApiClient->addConfiguration($userId, $configName);

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
        $this->requireNotBlocked($userId);
        Utils::validateConfigName($configName);

        # make sure config does exists
        if (!$this->db->isExistingConfiguration($userId, $configName)) {
            throw new NotFoundException('configuration with this name does not exist');
        }

        $this->VpnConfigApiClient->revokeConfiguration($userId, $configName);
        $this->db->revokeConfiguration($userId, $configName);
    }

    public function run(Request $request = null)
    {
        $response = parent::run($request);

        # CSP: https://developer.mozilla.org/en-US/docs/Security/CSP
        $response->setHeader('Content-Security-Policy', "default-src 'self'");
        # X-Frame-Options: https://developer.mozilla.org/en-US/docs/HTTP/X-Frame-Options
        $response->setHeader('X-Frame-Options', 'DENY');

        return $response;
    }

    private function isBlocked($userId)
    {
        return $this->db->isBlocked($userId);
    }

    private function requireNotBlocked($userId)
    {
        if ($this->isBlocked($userId)) {
            throw new ForbiddenException('user_blocked', 'the user was blocked by the administrator');
        }
    }
}
