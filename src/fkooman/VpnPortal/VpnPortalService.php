<?php

namespace fkooman\VpnPortal;

use fkooman\Rest\Service;
use Twig_Loader_Filesystem;
use Twig_Environment;

class VpnPortalService extends Service
{
    /** @var fkooman\VpnPortal\PdoStorage */
    private $db;

    /** @var fkooman\VpnPortal\VpnCertServiceClient */
    private $csc;

    public function __construct(PdoStorage $db, VpnCertServiceClient $csc)
    {
        parent::__construct();
        $this->db = $db;
        $this->csc = $csc;

        // use the Mellon plugin to retrieve user info
        $this->registerBeforeMatchingPlugin(new BasicAuthentication("foo", "bar", "Realmmmm"));

        /* GET */
        $this->get(
            '/manage',
            function () {
                $userId = "foo";
                $configs = $this->db->getConfigurations($userId);

                var_dump($configs);
                die();
                $loader = new Twig_Loader_Filesystem(
                    dirname(__DIR__)."/views"
                );
                $twig = new Twig_Environment($loader);

                return $twig->render(
                    "vpnPortal.twig",
                    array(
                        $configs,
                    )
                );
            }
        );

        /* POST */
        $this->post(
            '/manage',
            function (Request $request) {
                // FIXME: verify the Referer, CSRF?
                $userId = "foo";

                $configName = $request->getPostParameter('config_name');

                $this->db->addConfiguration($userId, $configName);
                $config = $this->csc->addConfiguration($userId, $configName);

                $response = new Response(201, "application/x-openvpn-profile");
                $response->setContent($config);

                return $respones;
            }
        );
    }
}
