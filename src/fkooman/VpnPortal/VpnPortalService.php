<?php

namespace fkooman\VpnPortal;

use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Rest\Service;
use fkooman\Rest\Plugin\BasicAuthentication;
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
            function () {
                $userId = "foo";
                $configs = $this->db->getConfigurations($userId);

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
            function (Request $request) {
                //var_dump($request);
                // FIXME: verify the Referer, CSRF?
                $userId = "foo";

                $configName = $request->getPostParameter('config_name');
                if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $configName)) {
                    throw new BadRequestException("invalid characters in config_name");
                }

                $this->db->addConfiguration($userId, $configName);
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
            function (Request $request) {
                $userId = "foo";

#                $pp = $request->getPostParameters();
#                $revokeList = $pp['revoke'];


                $revokeList = $request->getPostParameter('revoke');
                foreach ($revokeList as $configName) {
                    $this->db->revokeConfiguration($userId, $configName);
                }
                $response = new Response(302);
                $response->setHeader("Location", $request->getHeader("Referer"));

                return $response;
            }
        );
    }
}
