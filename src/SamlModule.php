<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use fkooman\SAML\SP\SP;
use fkooman\SeCookie\SessionInterface;
use LetsConnect\Common\Http\Exception\HttpException;
use LetsConnect\Common\Http\RedirectResponse;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Response;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\Http\ServiceModuleInterface;

class SamlModule implements ServiceModuleInterface
{
    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var string */
    private $spEntityId;

    /** @var \fkooman\SAML\SP\SP */
    private $sp;

    /** @var string|null */
    private $discoUrl;

    /** @var string|null */
    private $idpEntityId;

    /**
     * @param \fkooman\SeCookie\SessionInterface $session
     * @param string                             $spEntityId
     * @param \fkooman\SAML\SP\SP                $sp
     * @param string|null                        $idpEntityId
     * @param string|null                        $discoUrl
     */
    public function __construct(SessionInterface $session, $spEntityId, SP $sp, $idpEntityId, $discoUrl)
    {
        $this->session = $session;
        $this->spEntityId = $spEntityId;
        $this->sp = $sp;
        $this->idpEntityId = $idpEntityId;
        $this->discoUrl = $discoUrl;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/_saml/login',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $relayState = $request->getQueryParameter('ReturnTo');

                $authnContext = $this->session->has('_saml_auth_acr') ? $this->session->get('_saml_auth_acr') : [];
                $this->session->delete('_saml_auth_acr');

                // if and entityId is specified, run with it
                if (null !== $this->idpEntityId) {
                    return new RedirectResponse($this->sp->login($this->idpEntityId, $relayState, $authnContext));
                }

                // we didn't get an IdP entityId so we MUST perform discovery
                if (null === $this->discoUrl) {
                    throw new HttpException('no IdP specified, and no discovery service configured', 500);
                }

                if (null !== $idpEntityId = $request->getQueryParameter('IdP', false)) {
                    // we already came back from the discovery service, use
                    // this IdP
                    return new RedirectResponse($this->sp->login($idpEntityId, $relayState, $authnContext));
                }

                // we didn't come back from discovery, so send the browser there
                $discoQuery = http_build_query(
                    [
                        'entityID' => $this->spEntityId,
                        'returnIDParam' => 'IdP',
                        'return' => $request->getUri(),
                    ]
                );

                $querySeparator = false === strpos($this->discoUrl, '?') ? '?' : '&';

                return new RedirectResponse(
                    sprintf(
                        '%s%s%s',
                        $this->discoUrl,
                        $querySeparator,
                        $discoQuery
                    )
                );
            }
        );

        $service->post(
            '/_saml/acs',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $this->sp->handleResponse(
                    $request->getPostParameter('SAMLResponse')
                );

                return new RedirectResponse($request->getPostParameter('RelayState'));
            }
        );

        $service->get(
            '/_saml/logout',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $logoutUrl = $this->sp->logout($request->getQueryParameter('ReturnTo'));

                return new RedirectResponse($logoutUrl);
            }
        );

        $service->get(
            '/_saml/metadata',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $response = new Response(200, 'application/samlmetadata+xml');
                $response->setBody($this->sp->metadata());

                return $response;
            }
        );
    }
}
