<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\SAML\SP\IdPInfo;
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

    /** @var \fkooman\SAML\SP\IdPInfo */
    private $idpInfo;

    public function __construct(SessionInterface $session, IdPInfo $idpInfo)
    {
        $this->session = $session;
        $this->idpInfo = $idpInfo;
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

                return new RedirectResponse($sp->login($this->idpInfo, $relayState));
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
                    $this->idpInfo,
                    $request->getPostParameter('SAMLResponse')
                );

                return new RedirectResponse($request->getPostParameter('RelayState'));
            }
        );
    }
}
