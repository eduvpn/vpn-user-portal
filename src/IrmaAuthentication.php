<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Config;
use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Exception\HttpException;
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

<<<<<<< HEAD
    /** @var string */
    private $irmaServerUrl;

    /**
     * @param string $irmaServerUrl
     */
    public function __construct(SessionInterface $session, TplInterface $tpl, HttpClientInterface $httpClient, $irmaServerUrl)
=======
    /** @var \LC\Common\Config */
    private $config;

    public function __construct(SessionInterface $session, TplInterface $tpl, HttpClientInterface $httpClient, Config $config)
>>>>>>> upstream/irma
    {
        $this->session = $session;
        $this->tpl = $tpl;
        $this->httpClient = $httpClient;
<<<<<<< HEAD
        $this->irmaServerUrl = $irmaServerUrl;
=======
        $this->config = $config;
>>>>>>> upstream/irma
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
                $irmaToken = $request->requirePostParameter('irma_auth_token');
<<<<<<< HEAD
                $irmaStatusUrl = sprintf('%s/session/%s/result', $this->irmaServerUrl, $irmaToken);
=======
                $irmaStatusUrl = sprintf('%s/session/%s/result', $this->config->requireString('irmaServerUrl'), $irmaToken);
>>>>>>> upstream/irma
                $httpResponse = $this->httpClient->get($irmaStatusUrl, [], []);
                // @see https://irma.app/docs/api-irma-server/#get-session-token-result
                $jsonData = Json::decode($httpResponse->getBody());
                // validate the result
                if (!\array_key_exists('proofStatus', $jsonData)) {
                    throw new HttpException('missing "proofStatus"', 401);
                }
                // XXX we probably need to verify other items as well, but who
                // knows... can we even trust this information?
<<<<<<< HEAD
                if ('VALID' !== $jsonData['proofStatus'] || 'mysecrettoken' !== $jsonData['token']) {
                    throw new HttpException('"proofStatus" MUST be "VALID"', 401);
                }

                $userIdAttribute = 'pbdf.pbdf.email.email'; 
=======
                if ('VALID' !== $jsonData['proofStatus']) {
                    throw new HttpException('"proofStatus" MUST be "VALID"', 401);
                }

                $userIdAttribute = $this->config->requireString('userIdAttribute');
>>>>>>> upstream/irma
                $userId = null;
                // extract the attribute, WTF double array...
                foreach ($jsonData['disclosed'][0] as $attributeList) {
                    if ($userIdAttribute === $attributeList['id']) {
                        $userId = $attributeList['rawvalue'];
                    }
                }

                if (null === $userId) {
                    throw new HttpException('unable to extract "'.$userIdAttribute.'" attribute', 401);
                }

                $this->session->set('_irma_auth_user', $userId);
                // XXX redirect to correct place, probably put HTTP_REFERER in
                // form as well in template...
                return new RedirectResponse($request->getRootUri(), 302);
            }
        );
    }

    /**
     * @return \LC\Common\Http\UserInfo|\LC\Common\Http\Response|null
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
<<<<<<< HEAD
                    // XXX do we need the irmaServerUrl in the template?
                    //'irmaServerUrl' => $this->irmaServerUrl,
=======
                    'irmaServerUrl' => $this->config->requireString('irmaServerUrl'),
                    'userIdAttribute' => $this->config->requireString('userIdAttribute'),
>>>>>>> upstream/irma
                ]
            )
        );

        return $response;
    }
}
