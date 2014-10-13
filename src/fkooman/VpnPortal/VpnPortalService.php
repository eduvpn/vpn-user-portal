<?php

namespace fkooman\VpnPortal;

use fkooman\Http\Request;
use fkooman\Http\Response;
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

        /* GET */
        $this->get(
            '/manage',
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
            '/manage',
            function (Request $request, UserInfo $u) {
                // FIXME: CRSF protection?
                $configName = $request->getPostParameter('config_name');
                $this->validateConfigName($configName);

                $this->pdoStorage->addConfiguration($u->getUserId(), $configName);
                $vpnConfig = $this->vpnCertServiceClient->addConfiguration($u->getUserId(), $configName);

                $response = new Response(201, "application/x-openvpn-profile");
                $response->setHeader("Content-Disposition", sprintf('attachment; filename="%s.ovpn"', $configName));
                $response->setContent($vpnConfig);

                return $response;
            }
        );

        /* DELETE */
        $this->post(
            '/delete',
            function (Request $request, UserInfo $u) {
                // FIXME: CRSF protection?
                $revokeList = $request->getPostParameter('revoke');
                if (null === $revokeList || !is_array($revokeList)) {
                    throw new BadRequestException("missing or malformed revoke list");
                }
                foreach ($revokeList as $configName) {
                    $this->validateConfigName($configName);
                    $this->pdoStorage->revokeConfiguration($u->getUserId(), $configName);
                    $this->vpnCertServiceClient->revokeConfiguration($u->getUserId(), $configName);
                }
                $response = new Response(302);
                // FIXME: find better way to redirect back, do not use Referer!
                $response->setHeader("Location", $request->getHeader("Referer"));

                return $response;
            }
        );
    }

    private function validateConfigName($configName)
    {
        if (null === $configName) {
            throw new BadRequest("missing parameter");
        }
        if (!is_string($configName)) {
            throw new BadRequest("malformed parameter");
        }
        if (32 < strlen($configName)) {
            throw new BadRequest("name too long, maximum 32 characters");
        }
        // FIXME: less restrictive in supported characters...
        if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $configName)) {
            throw new BadRequestException("invalid characters in name");
        }
    }
}
