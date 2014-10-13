<?php

namespace fkooman\VpnPortal;

use fkooman\Rest\Service;
use Twig_Loader_Filesystem;
use Twig_Environment;

class VpnPortalService extends Service
{
    /** @var fkooman\VpnPortal\PdoStorage */
    private $db;

    public function __construct(PdoStorage $db)
    {
        parent::__construct();
        $this->db = $db;

        // use the Mellon plugin to retrieve user info
        // $this->registerBeforeMatchingPlugin(...);

        /* GET */
        $this->get(
            '/manage',
            function () {
                // get userinfo from melon
                // show stuff

                // $this->db->getUserConfigurations(...);

                #$loader = new Twig_Loader_Filesystem(
                #    dirname(__DIR__)."/views"
                #);
                #$twig = new Twig_Environment($loader);

                #$output = $twig->render(
                #    "vpnPortal.twig",
                #    array()
                #);
            }
        );

        /* POST */
        $this->post(
            '/manage',
            function (Request $request) {
                // get user info from melon to see if everything fits
                // verify the Referer

                $commonName = $request->getPostParameter('commonName');
                // generate a new config using the vpnCertClient
                // store metadata in the DB
                // send the file to the client
                $response = new Response();
                $response->setContent("...");

                return $response;
            }
        );
    }
}
