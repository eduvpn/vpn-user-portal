<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\Http\SessionInterface;
use LC\Common\Http\UserInfo;
use LC\Common\HttpClient\HttpClientInterface;
use LC\Common\Json;
use LC\Common\TplInterface;

class IrmaAuthentication implements ServiceModuleInterface, BeforeHookInterface
{
    /** @var \LC\Common\TplInterface */
    protected $tpl;

    /** @var SessionInterface */
    private $session;

    /** @var \LC\Common\HttpClient\HttpClientInterface */
    private $httpClient;

    /** @var string */
    private $irmaServerUrl;

    /**
     * @param string $irmaServerUrl
     */
    public function __construct(SessionInterface $session, TplInterface $tpl, HttpClientInterface $httpClient, $irmaServerUrl)
    {
        $this->session = $session;
        $this->tpl = $tpl;
        $this->httpClient = $httpClient;
        $this->irmaServerUrl = $irmaServerUrl;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->post(
            '/_irma/verify',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request) {
                // we need to verify the IRMA token we got from the web browser
                // here by calling the IRMA server API
                // XXX validate irmaToken to make sure it only contains chars we expect!
                $irmaToken = $request->requirePostParameter('irma_token');
                $irmaStatusUrl = sprintf('%s/session/%s/result', $this->irmaServerUrl, $irmaToken);
                $httpResponse = $this->httpClient->get($irmaStatusUrl, [], []);
//                $jsonData = Json::decode($httpResponse->getBody());

                // XXX validate the result...
                var_dump($httpResponse);
                exit();

                return new RedirectResponse('/', 302);
            }
        );
    }

    /**
     * @return \LC\Common\Http\UserInfo|\LC\Common\Http\Response
     */
    public function executeBefore(Request $request, array $hookData)
    {
        if (Service::isWhitelisted($request, ['POST' => ['/_irma/verify']])) {
            return null;
        }

        if (null !== $authUser = $this->session->get('_irma_auth_user')) {
            return new UserInfo(
                $authUser,
                []
            );
        }

        $response = new Response(200, 'text/html');
        $response->setBody(
            $this->tpl->render(
                'irmaAuthentication',
                [
                    // XXX do we need the irmaServerUrl in the template?
                    //'irmaServerUrl' => $this->irmaServerUrl,
                ]
            )
        );

        return $response;
    }
}
