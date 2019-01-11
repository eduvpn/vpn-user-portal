<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\SAML\SP\SP;
use fkooman\SeCookie\SessionInterface;
use SURFnet\VPN\Common\Http\Exception\HttpException;
use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;

class SamlModule implements ServiceModuleInterface
{
    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var string */
    private $spEntityId;

    /** @var \fkooman\SAML\SP\SP */
    private $sp;

    /** @var null|string */
    private $discoUrl;

    /** @var null|string */
    private $idpEntityId;

    /**
     * @param \fkooman\SeCookie\SessionInterface $session
     * @param string                             $spEntityId
     * @param \fkooman\SAML\SP\SP                $sp
     * @param null|string                        $idpEntityId
     * @param null|string                        $discoUrl
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
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $relayState = $request->getQueryParameter('ReturnTo');

                // if and entityId is specified, run with it
                if (null !== $this->idpEntityId) {
                    return new RedirectResponse($this->sp->login($this->idpEntityId, $relayState));
                }

                // we didn't get an IdP entityId so we MUST perform discovery
                if (null === $this->discoUrl) {
                    throw new HttpException('no IdP specified, and no discovery service configured', 500);
                }

                if (null !== $idpEntityId = $request->getQueryParameter('IdP', false)) {
                    // we already came back from the discovery service, use
                    // this IdP
                    return new RedirectResponse($this->sp->login($idpEntityId, $relayState));
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
             * @return \SURFnet\VPN\Common\Http\Response
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
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                if (null === $samlResponse = $request->getQueryParameter('SAMLResponse', false)) {
                    // this is NOT a response coming from an IdP, so we got
                    // redirected here because the user pused the "Logout"
                    // button...
                    $logoutUrl = $this->sp->logout($request->getQueryParameter('ReturnTo'));

                    return new RedirectResponse($logoutUrl);
                }

                // response from IdP
                $this->sp->handleLogoutResponse(
                    $samlResponse,
                    $request->getQueryParameter('RelayState'),
                    $request->getQueryParameter('Signature')
                );

                return new RedirectResponse($request->getQueryParameter('RelayState'));
            }
        );

        $service->get(
            '/_saml/metadata',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $response = new Response(200, 'application/samlmetadata+xml');
                $response->setBody($this->sp->metadata());

                return $response;
            }
        );
    }
}
