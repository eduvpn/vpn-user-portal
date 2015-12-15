<?php

namespace fkooman\VPN\UserPortal;

use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Http\RedirectResponse;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Http\Exception\NotFoundException;
use fkooman\Rest\Service;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;
use fkooman\Tpl\TemplateManagerInterface;
use ZipArchive;
use DomainException;

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

        $this->get(
            '/config/',
            function (Request $request) {
                return new RedirectResponse($request->getUrl()->getRootUrl(), 301);
            }
        );

        /* GET */
        $this->get(
            '/',
            function (UserInfoInterface $u) {
                return $this->getConfigurations($u->getUserId());
            }
        );

        /* GET */
        $this->get(
            '/:configName',
            function (UserInfoInterface $u, $configName) {
                return $this->getConfig($u->getUserId(), $configName);
            }
        );

        /* GET */
        $this->get(
            '/:configName/ovpn',
            function (UserInfoInterface $u, $configName) {
                return $this->getOvpnConfig($u->getUserId(), $configName);
            }
        );

        /* GET */
        $this->get(
            '/:configName/zip',
            function (UserInfoInterface $u, $configName) {
                return $this->getZipConfig($u->getUserId(), $configName);
            }
        );

        /* POST */
        $this->post(
            '/',
            function (Request $request, UserInfoInterface $u) {
                return $this->postConfig(
                    $u->getUserId(),
                    $request->getPostParameter('name'),
                    $request->getUrl()->getRootUrl()
                );
            }
        );

        /* DELETE */
        $this->delete(
            '/:configName',
            function (Request $request, UserInfoInterface $u, $configName) {
                return $this->deleteConfig(
                    $u->getUserId(),
                    $configName,
                    $request->getUrl()->getRootUrl()
                );
            }
        );
    }

    public function getConfigurations($userId)
    {
        $vpnConfigurations = $this->db->getConfigurations($userId);

        return $this->templateManager->render(
            'vpnPortal',
            array(
                'vpnConfigurations' => $vpnConfigurations,
                'isBlocked' => $this->isBlocked($userId),
            )
        );
    }

    public function getConfig($userId, $configName)
    {
        $this->requireNotBlocked($userId);
        Utils::validateConfigName($configName);
        if (!$this->db->isExistingConfiguration($userId, $configName)) {
            throw new NotFoundException('configuration not found');
        }
        $vpnConfig = $this->db->getConfiguration($userId, $configName);
        if (PdoStorage::STATUS_READY != $vpnConfig['status']) {
            throw new NotFoundException('configuration already downloaded');
        }

        return $this->templateManager->render(
            'vpnConfigDownload',
            array(
                'configName' => $configName,
            )
        );
    }

    private function getConfigData($userId, $configName)
    {
        Utils::validateConfigName($configName);
        if (!$this->db->isExistingConfiguration($userId, $configName)) {
            throw new NotFoundException('configuration not found');
        }

        $vpnConfig = $this->db->getConfiguration($userId, $configName);
        if (PdoStorage::STATUS_READY != $vpnConfig['status']) {
            throw new NotFoundException('configuration already downloaded');
        }

        $this->db->activateConfiguration($userId, $configName);

        // make call to vpn-config-api to retrieve the configuration
        return $this->VpnConfigApiClient->addConfiguration($userId, $configName);
    }

    public function getOvpnConfig($userId, $configName)
    {
        $this->requireNotBlocked($userId);
        $configData = $this->getConfigData($userId, $configName);
        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setHeader('Content-Disposition', sprintf('attachment; filename="%s.ovpn"', $configName));
        $response->setBody($configData);

        return $response;
    }

    public function getZipConfig($userId, $configName)
    {
        $this->requireNotBlocked($userId);
        $configData = $this->getConfigData($userId, $configName);
        $inlineTypeFileName = array(
            'ca' => sprintf('ca_%s.crt', $configName),
            'cert' => sprintf('client_%s.crt', $configName),
            'key' => sprintf('client_%s.key', $configName),
            'tls-auth' => sprintf('ta_%s.key', $configName),
        );

        $zipName = tempnam(sys_get_temp_dir(), 'vup_');
        $z = new ZipArchive();
        $z->open($zipName, ZipArchive::CREATE);

        foreach (array('cert', 'ca', 'key', 'tls-auth') as $inlineType) {
            $pattern = sprintf('/\<%s\>(.*)\<\/%s\>/msU', $inlineType, $inlineType);
            if (1 !== preg_match($pattern, $configData, $matches)) {
                throw new DomainException('inline type not found');
            }
            $configData = preg_replace(
                $pattern,
                sprintf(
                    '%s %s',
                    $inlineType,
                    $inlineTypeFileName[$inlineType]
                ),
                $configData
            );
            $z->addFromString($inlineTypeFileName[$inlineType], trim($matches[1]));
        }
        // remove "key-direction X" and add it to tls-auth line as last
        // parameter (hack to make NetworkManager import work)
        $configData = str_replace(
            array(
                'key-direction 1',
                sprintf('tls-auth ta_%s.key', $configName),
            ),
            array(
                '',
                sprintf('tls-auth ta_%s.key 1', $configName),
            ),
            $configData
        );

        $z->addFromString(sprintf('%s.ovpn', $configName), $configData);
        $z->close();

        $response = new Response(200, 'application/zip');
        $response->setHeader('Content-Disposition', sprintf('attachment; filename="%s.zip"', $configName));
        $response->setBody(file_get_contents($zipName));

        unlink($zipName);

        return $response;
    }

    public function postConfig($userId, $configName, $returnUri)
    {
        $this->requireNotBlocked($userId);
        Utils::validateConfigName($configName);
        if ($this->db->isExistingConfiguration($userId, $configName)) {
            throw new BadRequestException('configuration with this name already exists for this user');
        }
        $this->db->addConfiguration($userId, $configName);

        return new RedirectResponse(
            sprintf('%s/%s', $returnUri, $configName)
        );
    }

    public function deleteConfig($userId, $configName, $returnUri)
    {
        $this->requireNotBlocked($userId);
        Utils::validateConfigName($configName);
        $this->VpnConfigApiClient->revokeConfiguration($userId, $configName);
        $this->db->revokeConfiguration($userId, $configName);

        return new RedirectResponse($returnUri);
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
