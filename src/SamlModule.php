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
use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;

class SamlModule implements ServiceModuleInterface
{
    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \fkooman\SAML\SP\SP */
    private $sp;

    /** @var string */
    private $discoUrl;

    /**
     * @param \fkooman\SeCookie\SessionInterface $session
     * @param \fkooman\SAML\SP\SP                $sp
     * @param string                             $discoUrl
     */
    public function __construct(SessionInterface $session, SP $sp, $discoUrl)
    {
        $this->session = $session;
        $this->sp = $sp;
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
                // assume we have disco for now!
                if (null === $idpEntityId = $request->getQueryParameter('IdP', false)) {
                    // go to disco
                    $discoQuery = http_build_query(
                        [
                            // XXX entityID from SP object
                            'entityID' => $request->getRootUri().'_saml/metadata',
                            'returnIDParam' => 'IdP',
                            'return' => $request->getUri(),
                        ]
                    );

                    // XXX figure out the query separator
                    return new RedirectResponse($this->discoUrl.'?'.$discoQuery);
                }

                // we figured out which IdP to use!
                $relayState = $request->getQueryParameter('ReturnTo');

                return new RedirectResponse($this->sp->login($idpEntityId, $relayState));
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
                    // this is NOT a response coming from an IdP, so it is triggered by the app
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
    }
}
