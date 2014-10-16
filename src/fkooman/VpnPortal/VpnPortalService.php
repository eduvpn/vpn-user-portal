<?php

namespace fkooman\VpnPortal;

use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Http\RedirectResponse;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Rest\Service;
use fkooman\Rest\ServicePluginInterface;
use fkooman\Rest\Plugin\UserInfo;
use Twig_Loader_Filesystem;
use Twig_Environment;

class VpnPortalService extends Service
{
    /** @var fkooman\VpnPortal\PdoStorage */
    private $pdoStorage;

    /** @var fkooman\VpnPortal\VpnCertServiceClient */
    private $vpnCertServiceClient;

    public function __construct(PdoStorage $pdoStorage, ServicePluginInterface $authenticationPlugin, VpnCertServiceClient $vpnCertServiceClient)
    {
        parent::__construct();
        $this->pdoStorage = $pdoStorage;
        $this->vpnCertServiceClient = $vpnCertServiceClient;

        $this->registerBeforeMatchingPlugin($authenticationPlugin);

        $this->setDefaultRoute('/config/');

        /* GET */
        $this->get(
            '/config/',
            function (UserInfo $u) {
                $configs = $this->pdoStorage->getConfigurations($u->getUserId());

                $loader = new Twig_Loader_Filesystem(
                    dirname(dirname(dirname(__DIR__)))."/views"
                );
                $twig = new Twig_Environment($loader);

                return $twig->render(
                    "vpnPortal.twig",
                    array(
                        "configs" => $configs,
                    )
                );
            }
        );

        /* POST */
        $this->post(
            '/config/',
            function (Request $request, UserInfo $u) {
                // FIXME: CRSF protection?
                $configName = $request->getPostParameter('name');
                $this->validateConfigName($configName);

                $vpnConfig = $this->vpnCertServiceClient->addConfiguration($u->getUserId(), $configName);
                $this->pdoStorage->addConfiguration($u->getUserId(), $configName);

                $response = new Response(201, "application/x-openvpn-profile");
                $response->setHeader("Content-Disposition", sprintf('attachment; filename="%s.ovpn"', $configName));
                $response->setContent($vpnConfig);

                return $response;
            }
        );

        /* DELETE */
        $this->delete(
            '/config/:configName',
            function (Request $request, UserInfo $u, $configName) {
                // FIXME: CRSF protection?
                $this->validateConfigName($configName);
                $this->vpnCertServiceClient->revokeConfiguration($u->getUserId(), $configName);
                $this->pdoStorage->revokeConfiguration($u->getUserId(), $configName);

                return new RedirectResponse("http://foo.example.org/config/");
            }
        );
    }

    private function validateConfigName($configName)
    {
        if (null === $configName) {
            throw new BadRequestException("missing parameter");
        }
        if (!is_string($configName)) {
            throw new BadRequestException("malformed parameter");
        }
        if (32 < strlen($configName)) {
            throw new BadRequestException("name too long, maximum 32 characters");
        }
        // FIXME: be less restrictive in supported characters...
        if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $configName)) {
            throw new BadRequestException("invalid characters in name");
        }
    }
}
