<?php

namespace fkooman\VpnPortal;

use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Http\RedirectResponse;
use fkooman\Http\Exception\BadRequestException;
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

        /* POST */
        $this->post(
            '/config/',
            function (Request $request, MellonUserInfo $u) use ($compatThis) {
                if ($request->getHeader('Referer') !== $request->getRequestUri()->getUri()) {
                    throw new BadRequestException('csrf protection triggered');
                }

                return $compatThis->postConfig(
                    $u->getUserId(),
                    $request->getPostParameter('name')
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

    public function postConfig($userId, $configName)
    {
        if ($this->pdoStorage->isExistingConfiguration($userId, $configName)) {
            throw new BadRequestException('configuration with this name already exists for this user');
        }
        $vpnConfig = $this->vpnCertServiceClient->addConfiguration($userId, $configName);
        $this->pdoStorage->addConfiguration($userId, $configName);

        $response = new Response(201, 'application/x-openvpn-profile');
        $response->setHeader('Content-Disposition', sprintf('attachment; filename="%s.ovpn"', $configName));
        $response->setContent($vpnConfig);

        return $response;
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
