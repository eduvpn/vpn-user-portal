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

    /** @var \LC\Common\Config */
    private $config;

    public function __construct(SessionInterface $session, TplInterface $tpl, HttpClientInterface $httpClient, Config $config)
    {
        $this->session = $session;
        $this->tpl = $tpl;
        $this->httpClient = $httpClient;
        $this->config = $config;
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
                if (null === $sessionToken = $this->session->get('_irma_auth_token')) {
                    throw new HttpException('token not found in session', 400);
                }

                $irmaStatusUrl = sprintf('%s/session/%s/result', $this->config->requireString('irmaServerUrl'), $sessionToken);
                $httpResponse = $this->httpClient->get($irmaStatusUrl, [], []);
                // @see https://irma.app/docs/api-irma-server/#get-session-token-result
                $jsonData = Json::decode($httpResponse->getBody());
                // validate the result
                if (!\array_key_exists('proofStatus', $jsonData)) {
                    throw new HttpException('missing "proofStatus"', 401);
                }
                // XXX we probably need to verify other items as well, but who
                // knows... can we even trust this information?
                if ('VALID' !== $jsonData['proofStatus']) {
                    throw new HttpException('"proofStatus" MUST be "VALID"', 401);
                }

                $userIdAttribute = $this->config->requireString('userIdAttribute');
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

        // @see https://irma.app/docs/getting-started/#perform-a-session
        $httpResponse = $this->httpClient->postJson(
            $this->config->requireString('irmaServerUrl').'/session',
            [],
            [
                '@context' => 'https://irma.app/ld/request/disclosure/v2',
                'disclose' => [
                    [
                        [
                            $this->config->requireString('userIdAttribute'),
                        ],
                    ],
                ],
            ],
            [
                'Authorization: '.$this->config->requireString('secretToken'),
            ]
        );

        $jsonData = Json::decode($httpResponse->getBody());
        if (!\array_key_exists('sessionPtr', $jsonData)) {
            throw new HttpException('"sessionPtr" not available JSON response', 500);
        }
        // extract "token" and store it in the session to be used
        // @ verification stage
        if (!\array_key_exists('token', $jsonData)) {
            throw new HttpException('"token" not available in JSON response', 500);
        }
        $sessionToken = $jsonData['token'];
        $this->session->set('_irma_auth_token', $sessionToken);

        // extract sessionPtr and make available to frontend
        $sessionPtr = Json::encode($jsonData['sessionPtr']);

        $response = new Response(200, 'text/html');
        $response->setBody(
            $this->tpl->render(
                'irmaAuthentication',
                [
                    'sessionPtr' => $sessionPtr,
                ]
            )
        );

        return $response;
    }
}
