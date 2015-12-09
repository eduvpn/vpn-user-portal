<?php

namespace fkooman\VpnPortal;

use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Http\JsonResponse;
use fkooman\Http\RedirectResponse;
use fkooman\Http\Exception\BadRequestException;
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

    /** @var VpnCertServiceClient */
    private $vpnCertServiceClient;

    public function __construct(PdoStorage $db, TemplateManagerInterface $templateManager, VpnCertServiceClient $vpnCertServiceClient)
    {
        parent::__construct();

        $this->db = $db;
        $this->templateManager = $templateManager;
        $this->vpnCertServiceClient = $vpnCertServiceClient;

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
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('user'),
                ),
            )
        );

        /* GET */
        $this->get(
            '/:configName',
            function (UserInfoInterface $u, $configName) {
                return $this->getConfig($u->getUserId(), $configName);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('user'),
                ),
            )
        );

        /* GET */
        $this->get(
            '/:configName/ovpn',
            function (UserInfoInterface $u, $configName) {
                return $this->getOvpnConfig($u->getUserId(), $configName);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('user'),
                ),
            )
        );

        /* GET */
        $this->get(
            '/:configName/zip',
            function (UserInfoInterface $u, $configName) {
                return $this->getZipConfig($u->getUserId(), $configName);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('user'),
                ),
            )
        );

        /* POST */
        $this->post(
            '/',
            function (Request $request, UserInfoInterface $u) {
                return $this->postConfig(
                    $u->getUserId(),
                    $request->getPostParameter('name'),
                    $request->getHeader('Referer')
                );
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('user'),
                ),
            )
        );

        /* DELETE */
        $this->delete(
            '/:configName',
            function (Request $request, UserInfoInterface $u, $configName) {
                return $this->deleteConfig(
                    $u->getUserId(),
                    $configName,
                    $request->getHeader('Referer')
                );
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('user'),
                ),
            )
        );

        // API for vpn-admin-portal
        $this->post(
            '/api/revoke',
            function (Request $request) {
                $userId = $request->getPostParameter('user_id');
                $configName = $request->getPostParameter('config_name');

                // XXX: validate user_id
                $this->validateConfigName($configName);
                $this->vpnCertServiceClient->revokeConfiguration($userId, $configName);
                $this->db->revokeConfiguration($userId, $configName);

                return new JsonResponse();
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('api'),
                ),
            )
        );

        $this->get(
            '/api/configurations',
            function (Request $request) {
                $allConfigurations = $this->db->getAllConfigurations();
                $response = new JsonResponse();
                $response->setBody($allConfigurations);

                return $response;
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('api'),
                ),
            )
        );
    }

    public function getConfigurations($userId)
    {
        $vpnConfigurations = $this->db->getConfigurations($userId);

        return $this->templateManager->render(
            'vpnPortal',
            array(
                'vpnConfigurations' => $vpnConfigurations,
            )
        );
    }

    public function getConfig($userId, $configName)
    {
        $this->validateConfigName($configName);
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

    public function getConfigData($userId, $configName)
    {
        $this->validateConfigName($configName);
        if (!$this->db->isExistingConfiguration($userId, $configName)) {
            throw new NotFoundException('configuration not found');
        }
        $vpnConfig = $this->db->getConfiguration($userId, $configName);
        if (PdoStorage::STATUS_READY != $vpnConfig['status']) {
            throw new NotFoundException('configuration already downloaded');
        }

        $this->db->activateConfiguration($userId, $configName);

        return $vpnConfig['config'];
    }

    public function getOvpnConfig($userId, $configName)
    {
        $configData = $this->getConfigData($userId, $configName);
        $response = new Response(200, 'application/x-openvpn-profile');
        $response->setHeader('Content-Disposition', sprintf('attachment; filename="%s.ovpn"', $configName));
        $response->setBody($configData);

        return $response;
    }

    public function getZipConfig($userId, $configName)
    {
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

        $response = new Response(201, 'application/zip');
        $response->setHeader('Content-Disposition', sprintf('attachment; filename="%s.zip"', $configName));
        $response->setBody(file_get_contents($zipName));

        unlink($zipName);

        return $response;
    }

    public function postConfig($userId, $configName, $returnUri)
    {
        $this->validateConfigName($configName);
        if ($this->db->isExistingConfiguration($userId, $configName)) {
            throw new BadRequestException('configuration with this name already exists for this user');
        }
        $vpnConfig = $this->vpnCertServiceClient->addConfiguration($userId, $configName);
        $this->db->addConfiguration($userId, $configName, $vpnConfig);

        return new RedirectResponse($returnUri);
    }

    public function deleteConfig($userId, $configName, $returnUri)
    {
        $this->validateConfigName($configName);
        $this->vpnCertServiceClient->revokeConfiguration($userId, $configName);
        $this->db->revokeConfiguration($userId, $configName);

        return new RedirectResponse($returnUri);
    }

    private function validateConfigName($configName)
    {
        if (null === $configName) {
            throw new BadRequestException('missing parameter');
        }
        if (!is_string($configName)) {
            throw new BadRequestException('malformed parameter');
        }
        if (32 < strlen($configName)) {
            throw new BadRequestException('name too long, maximum 32 characters');
        }
        // XXX: be less restrictive in supported characters...
        if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $configName)) {
            throw new BadRequestException('invalid characters in name');
        }
    }
}
