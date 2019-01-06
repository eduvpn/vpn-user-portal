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
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;

class SamlModule implements ServiceModuleInterface
{
    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var array<string,\fkooman\SAML\SP\IdPInfo> */
    private $idpInfoList;

    /** @var null|string */
    private $discoUrl;

    /**
     * @param \fkooman\SeCookie\SessionInterface $session
     * @param array                              $idpInfoList
     * @param null|string                        $discoUrl
     */
    public function __construct(SessionInterface $session, array $idpInfoList, $discoUrl)
    {
        $this->session = $session;
        $this->idpInfoList = $idpInfoList;
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
                $entityId = $request->getRootUri().'_saml/metadata';
                $acsUrl = $request->getRootUri().'_saml/acs';
                $sp = new SP($entityId, $acsUrl);
                // XXX we have to figure out if this works with a 'discovery' service
                $relayState = $this->session->get('_saml_auth_return_to');

                // determine the IdP, if there is only 1 it is easy...
                if (1 === \count($this->idpInfoList)) {
                    $idpInfo = $this->idpInfoList[array_keys($this->idpInfoList)[0]];
                } else {
                    if (null === $idpEntityId = $request->getQueryParameter('IdP', false)) {
                        // we don't know which IdP to forward the user to...
                        // perform discovery if discoUrl is set
                        if (null !== $this->discoUrl) {
                            $discoQuery = http_build_query(
                                [
                                    'entityID' => $request->getRootUri().'_saml/metadata',
                                    'returnIDParam' => 'IdP',
                                    'return' => $request->getRootUri().'_saml/login?foo=bar',
                                ]
                            );

                            return new RedirectResponse($this->discoUrl.'?'.$discoQuery);
                        }

                        throw new HttpException('missing "IdP" query parameter', 400);
                    }
                    $idpInfo = $this->idpInfoList[$idpEntityId];
                }

                return new RedirectResponse($sp->login($idpInfo, $relayState));
            }
        );

        $service->post(
            '/_saml/acs',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $entityId = $request->getRootUri().'_saml/metadata';
                $acsUrl = $request->getRootUri().'_saml/acs';
                $sp = new SP($entityId, $acsUrl);
                $sp->handleResponse(
                    $request->getPostParameter('SAMLResponse')
                );

                return new RedirectResponse($request->getPostParameter('RelayState'));
            }
        );
    }
}
