<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\SAML\SP\Exception\SamlException;
use fkooman\SAML\SP\SP;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;

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
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                try {
                    $returnTo = $request->getQueryParameter('ReturnTo');
                    $idpEntityId = $request->getQueryParameter('IdP', false);
                    $authnContextQuery = $request->getQueryParameter('AuthnContext', false);
                    $authnContextList = null !== $authnContextQuery ? explode(',', $authnContextQuery) : [];

                    // verify the requested AuthnContext in the whitelist
                    $acceptableAuthnContextList = [
                        'urn:oasis:names:tc:SAML:2.0:ac:classes:Password',
                        'urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport',
                        'urn:oasis:names:tc:SAML:2.0:ac:classes:X509',
                        'urn:oasis:names:tc:SAML:2.0:ac:classes:TimesyncToken',
                        // https://wiki.surfnet.nl/display/SsID/Using+Levels+of+Assurance+to+express+strength+of+authentication
                        'http://test.surfconext.nl/assurance/loa1',
                        'http://pilot.surfconext.nl/assurance/loa1',
                        'http://surfconext.nl/assurance/loa1',
                        'http://test.surfconext.nl/assurance/loa2',
                        'http://pilot.surfconext.nl/assurance/loa2',
                        'http://surfconext.nl/assurance/loa2',
                        'http://test.surfconext.nl/assurance/loa3',
                        'http://pilot.surfconext.nl/assurance/loa3',
                        'http://surfconext.nl/assurance/loa3',
                    ];
                    foreach ($authnContextList as $authnContext) {
                        if (!\in_array($authnContext, $acceptableAuthnContextList, true)) {
                            throw new HttpException(sprintf('unsupported requested AuthnContext "%s"', $authnContext), 400);
                        }
                    }

                    // if an entityId is specified, use it
                    if (null !== $idpEntityId) {
                        return new RedirectResponse($this->samlSp->login($idpEntityId, $returnTo, $authnContextList));
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
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                try {
                    $returnTo = $this->samlSp->handleResponse(
                        $request->getPostParameter('SAMLResponse'),
                        $request->getPostParameter('RelayState')
                    );

                    return new RedirectResponse($returnTo);
                } catch (SamlException $e) {
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->get(
            '/_saml/logout',
            /**
             * @return \LC\Common\Http\Response
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
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                try {
                    $returnTo = $this->samlSp->handleLogoutResponse(
                        $request->getQueryString()
                    );

                    return new RedirectResponse($returnTo);
                } catch (SamlException $e) {
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->get(
            '/_saml/logout',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $logoutUrl = $this->samlSp->logout($request->getQueryParameter('ReturnTo'));

                return new RedirectResponse($logoutUrl);
            }
        );

        $service->get(
            '/_saml/metadata',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $response = new Response(200, 'application/samlmetadata+xml');
                $response->setBody($this->samlSp->metadata());

                return $response;
            }
        );
    }
}
