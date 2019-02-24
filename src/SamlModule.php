<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use fkooman\SAML\SP\Exception\SamlException;
use fkooman\SAML\SP\SP;
use LetsConnect\Common\Http\Exception\HttpException;
use LetsConnect\Common\Http\RedirectResponse;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Response;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\Http\ServiceModuleInterface;

class SamlModule implements ServiceModuleInterface
{
    /** @var \fkooman\SAML\SP\SP */
    private $samlSp;

    /** @var string|null */
    private $discoUrl;

    /**
     * @param \fkooman\SAML\SP\SP $samlSp
     * @param string|null         $discoUrl
     */
    public function __construct(SP $samlSp, $discoUrl)
    {
        $this->samlSp = $samlSp;
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
                try {
                    $relayState = $request->getQueryParameter('ReturnTo');
                    $idpEntityId = $request->getQueryParameter('IdP', false);
                    $authnContextQuery = $request->getQueryParameter('AuthnContext', false);
                    // XXX is this safe (enough?)
                    $authnContext = null !== $authnContextQuery ? explode(',', $authnContextQuery) : [];

                    // if and entityId is specified, use it
                    if (null !== $idpEntityId) {
                        return new RedirectResponse($this->samlSp->login($idpEntityId, $relayState, $authnContext));
                    }

                    // we didn't get an IdP entityId so we MUST perform discovery
                    if (null === $this->discoUrl) {
                        throw new HttpException('no IdP specified, and no discovery service configured', 500);
                    }

                    // perform discovery
                    $discoQuery = http_build_query(
                        [
                            'entityID' => $this->samlSp->getSpInfo()->getEntityId(),
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
                } catch (SamlException $e) {
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->post(
            '/_saml/acs',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                try {
                    $this->samlSp->handleResponse(
                        $request->getPostParameter('SAMLResponse')
                    );

                    return new RedirectResponse($request->getPostParameter('RelayState'));
                } catch (SamlException $e) {
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->get(
            '/_saml/logout',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                try {
                    $logoutUrl = $this->samlSp->logout($request->getQueryParameter('ReturnTo'));

                    return new RedirectResponse($logoutUrl);
                } catch (SamlException $e) {
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->get(
            '/_saml/slo',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                try {
                    $this->samlSp->handleLogoutResponse(
                        $request->getQueryParameter('SAMLResponse'),
                        $request->getQueryParameter('RelayState'),
                        $request->getQueryParameter('Signature')
                    );

                    return new RedirectResponse($request->getQueryParameter('RelayState'));
                } catch (SamlException $e) {
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->get(
            '/_saml/logout',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $logoutUrl = $this->samlSp->logout($request->getQueryParameter('ReturnTo'));

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
                $response->setBody($this->samlSp->metadata());

                return $response;
            }
        );
    }
}
