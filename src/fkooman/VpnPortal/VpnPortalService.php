<?php

namespace fkooman\VpnPortal;

use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Rest\Service;
use fkooman\Rest\Plugin\BasicAuthentication;
use fkooman\Rest\Plugin\UserInfo;
use Twig_Loader_Filesystem;
use Twig_Environment;

class VpnPortalService extends Service
{
    /** @var fkooman\VpnPortal\PdoStorage */
    private $db;

    /** @var fkooman\VpnPortal\VpnCertServiceClient */
    private $csc;

    public function __construct(PdoStorage $db) //, VpnCertServiceClient $csc)
    {
        parent::__construct();
        $this->db = $db;
        //$this->csc = $csc;

        // use the Mellon plugin to retrieve user info
        $this->registerBeforeMatchingPlugin(new BasicAuthentication("foo", '$2y$10$zx/fEKn2yleZVULfL8bAt.vUg7OSOkzj1VB1PT2jRAqJ4qrDQOypS', "Realmmmm"));

        /* GET */
        $this->get(
            '/manage',
            function (UserInfo $u) {
                $configs = $this->db->getConfigurations($u->getUserId());

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
                if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $configName)) {
                    throw new BadRequestException("invalid characters in config_name");
                }

                $this->db->addConfiguration($u->getUserId(), $configName);
                //$config = $this->csc->addConfiguration($userId, $configName);
                $config = "PLACEHOLDER FILE";

                $response = new Response(201, "application/x-openvpn-profile");
                $response->setHeader("Content-Disposition", sprintf('attachment; filename="%s.ovpn"', $configName));
                $response->setContent($config);

                return $response;
            }
        );

        /* DELETE */
        $this->post(
            '/delete',
            function (Request $request, UserInfo $u) {
                // FIXME: CRSF protection?
                $revokeList = $request->getPostParameter('revoke');
                foreach ($revokeList as $configName) {
                    $this->db->revokeConfiguration($u->getUserId(), $configName);
                    //$this->csc->revokeConfiguration($userId, $configName);
                }
                $response = new Response(302);
                // FIXME: find better way to redirect back, do not use Referer!
                $response->setHeader("Location", $request->getHeader("Referer"));

                return $response;
            }
        );
    }
}
