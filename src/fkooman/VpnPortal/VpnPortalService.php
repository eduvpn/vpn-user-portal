<?php

namespace fkooman\VpnPortal;

use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Http\RedirectResponse;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\Exception\NotFoundException;
use fkooman\Rest\Service;
use fkooman\Rest\Plugin\Mellon\MellonUserInfo;
use Twig_Loader_Filesystem;
use Twig_Environment;

class VpnPortalService extends Service
{
    /** @var fkooman\VpnPortal\PdoStorage */
    private $pdoStorage;

    /** @var fkooman\VpnPortal\VpnCertServiceClient */
    private $vpnCertServiceClient;

    public function __construct(PdoStorage $pdoStorage, VpnCertServiceClient $vpnCertServiceClient)
    {
        parent::__construct();

        $this->pdoStorage = $pdoStorage;
        $this->vpnCertServiceClient = $vpnCertServiceClient;

        $this->setDefaultRoute('/config/');

        // in PHP 5.3 we cannot use $this from a closure
        $compatThis = &$this;

        $this->get(
            '/',
            function () {
                return new RedirectResponse('config/');
            }
        );

        /* GET */
        $this->get(
            '/config/',
            function (MellonUserInfo $u) use ($compatThis) {
                return $compatThis->getConfigurations($u->getUserId());
            }
        );

        /* GET */
        $this->get(
            '/config/:configName',
            function (MellonUserInfo $u, $configName) use ($compatThis) {
                return $compatThis->getConfig($u->getUserId(), $configName);
            }
        );

        /* GET */
        $this->get(
            '/config/:configName/ovpn',
            function (MellonUserInfo $u, $configName) use ($compatThis) {
                return $compatThis->getOvpnConfig($u->getUserId(), $configName);
            }
        );

        /* GET */
        $this->get(
            '/config/:configName/zip',
            function (MellonUserInfo $u, $configName) use ($compatThis) {
                return $compatThis->getZipConfig($u->getUserId(), $configName);
            }
        );

        /* POST */
        $this->post(
            '/config/',
            function (Request $request, MellonUserInfo $u) use ($compatThis) {
                if ($request->getHeader('Referer') !== $request->getRequestUri()->getUri()) {
                    throw new BadRequestException('csrf protection triggered');
                }

                return $compatThis->postConfig(
                    $u->getUserId(),
                    $request->getPostParameter('name'),
                    $request->getHeader('Referer')
                );
            }
        );

        /* DELETE */
        $this->delete(
            '/config/:configName',
            function (Request $request, MellonUserInfo $u, $configName) use ($compatThis) {
                if ($request->getHeader('Referer') !== sprintf('%s/', dirname($request->getRequestUri()->getUri()))) {
                    throw new BadRequestException('csrf protection triggered');
                }

                return $compatThis->deleteConfig(
                    $u->getUserId(),
                    $configName,
                    $request->getHeader('Referer')
                );
            }
        );
    }

    public function getConfigurations($userId)
    {
        $configs = $this->pdoStorage->getConfigurations($userId);

        $loader = new Twig_Loader_Filesystem(
            dirname(dirname(dirname(__DIR__))).'/views'
        );
        $twig = new Twig_Environment($loader);

        return $twig->render(
            'vpnPortal.twig',
            array(
                'configs' => $configs,
            )
        );
    }

    public function getConfig($userId, $configName)
    {
        $this->validateConfigName($configName);
        if (!$this->pdoStorage->isExistingConfiguration($userId, $configName)) {
            throw new NotFoundException('configuration not found');
        }
        $vpnConfig = $this->pdoStorage->getConfiguration($userId, $configName);
        if (PdoStorage::STATUS_READY != $vpnConfig['status']) {
            throw new NotFoundException('configuration already downloaded');
        }

        $loader = new Twig_Loader_Filesystem(
            dirname(dirname(dirname(__DIR__))).'/views'
        );
        $twig = new Twig_Environment($loader);

        return $twig->render(
            'vpnConfigDownload.twig',
            array(
                'configName' => $configName,
                'plainConfig' => $vpnConfig['config']
            )
        );
    }

    public function getOvpnConfig($userId, $configName)
    {
        $this->validateConfigName($configName);
        if (!$this->pdoStorage->isExistingConfiguration($userId, $configName)) {
            throw new NotFoundException('configuration not found');
        }
        $vpnConfig = $this->pdoStorage->getConfiguration($userId, $configName);
        if (PdoStorage::STATUS_READY != $vpnConfig['status']) {
            throw new NotFoundException('configuration already downloaded');
        }

        $this->pdoStorage->activateConfiguration($userId, $configName);
        $response = new Response(201, 'application/x-openvpn-profile');
        $response->setHeader('Content-Disposition', sprintf('attachment; filename="%s.ovpn"', $configName));
        $response->setContent($vpnConfig['config']);

        return $response;
    }

    public function postConfig($userId, $configName, $returnUri)
    {
        $this->validateConfigName($configName);
        if ($this->pdoStorage->isExistingConfiguration($userId, $configName)) {
            throw new BadRequestException('configuration with this name already exists for this user');
        }
        $vpnConfig = $this->vpnCertServiceClient->addConfiguration($userId, $configName);
        $this->pdoStorage->addConfiguration($userId, $configName, $vpnConfig);

        return new RedirectResponse($returnUri);
    }

    public function deleteConfig($userId, $configName, $returnUri)
    {
        $this->validateConfigName($configName);
        $this->vpnCertServiceClient->revokeConfiguration($userId, $configName);
        $this->pdoStorage->revokeConfiguration($userId, $configName);

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
        // FIXME: be less restrictive in supported characters...
        if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $configName)) {
            throw new BadRequestException('invalid characters in name');
        }
    }
}
