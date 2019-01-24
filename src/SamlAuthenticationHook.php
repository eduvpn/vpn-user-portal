<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use fkooman\SAML\SP\SP;
use LetsConnect\Common\Http\BeforeHookInterface;
use LetsConnect\Common\Http\Exception\HttpException;
use LetsConnect\Common\Http\RedirectResponse;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\Http\UserInfo;

class SamlAuthenticationHook implements BeforeHookInterface
{
    /** @var \fkooman\SAML\SP\SP */
    private $samlSp;

    /** @var string|null */
    private $idpEntityId;

    /** @var string */
    private $userIdAttribute;

    /** @var string|null */
    private $entitlementAttribute;

    /** @var array<string> */
    private $authnContext;

    /** @var array<string,array<string>> */
    private $entitlementAuthnContext;

    /**
     * @param \fkooman\SAML\SP\SP         $samlSp
     * @param string|null                 $idpEntityId
     * @param string                      $userIdAttribute
     * @param string|null                 $entitlementAttribute
     * @param array<string>               $authnContext
     * @param array<string,array<string>> $entitlementAuthnContext
     */
    public function __construct(SP $samlSp, $idpEntityId, $userIdAttribute, $entitlementAttribute, array $authnContext, array $entitlementAuthnContext)
    {
        $this->samlSp = $samlSp;
        $this->idpEntityId = $idpEntityId;
        $this->userIdAttribute = $userIdAttribute;
        $this->entitlementAttribute = $entitlementAttribute;
        $this->authnContext = $authnContext;
        $this->entitlementAuthnContext = $entitlementAuthnContext;
    }

    /**
     * @param \LetsConnect\Common\Http\Request $request
     * @param array                            $hookData
     *
     * @return false|\LetsConnect\Common\Http\RedirectResponse|\LetsConnect\Common\Http\UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
    {
        $whiteList = [
            'POST' => [
                '/_saml/acs',
                '/_logout',
            ],
            'GET' => [
                '/_saml/login',
                '/_saml/logout',
                '/_saml/metadata',
            ],
        ];
        if (Service::isWhitelisted($request, $whiteList)) {
            return false;
        }

        if (false === $samlAssertion = $this->samlSp->getAssertion()) {
            // user not (yet) authenticated, redirect to "login" endpoint
            return self::getLoginRedirect($request, $this->idpEntityId, $this->authnContext);
        }

        $idpEntityId = $samlAssertion->getIssuer();
        $samlAttributes = $samlAssertion->getAttributes();

        if (!array_key_exists($this->userIdAttribute, $samlAttributes)) {
            throw new HttpException(sprintf('missing SAML user_id attribute "%s"', $this->userIdAttribute), 500);
        }

        $userId = $samlAttributes[$this->userIdAttribute][0];
        // remove "NameID" XML construction if it is there, e.g. for
        // eduPersonTargetedID
        $userId = strip_tags($userId);

        $userAuthnContext = $samlAssertion->getAuthnContext();
        if (null !== $this->entitlementAttribute) {
            $userEntitlements = $samlAttributes[$this->entitlementAttribute];

            // if we got an entitlement that's part of the
            // entitlementAuthnContext we have to make sure we have one of
            // the listed AuthnContexts
            foreach ($this->entitlementAuthnContext as $entitlement => $authnContext) {
                if (\in_array($entitlement, $userEntitlements, true)) {
                    if (!\in_array($userAuthnContext, $authnContext, true)) {
                        // we do not have the required AuthnContext, trigger login
                        // and request the first acceptable AuthnContext
                        return self::getLoginRedirect($request, $idpEntityId, $authnContext);
                    }
                }
            }
        }

        return new UserInfo(
            $userId,
            $this->getEntitlementList($idpEntityId, $samlAttributes),
            $samlAssertion->getAuthnInstant()
        );
    }

    /**
     * @param string                      $idpEntityId
     * @param array<string,array<string>> $samlAttributes
     *
     * @return array<int,string>
     */
    private function getEntitlementList($idpEntityId, array $samlAttributes)
    {
        if (null === $this->entitlementAttribute) {
            return [];
        }
        if (!array_key_exists($this->entitlementAttribute, $samlAttributes)) {
            return [];
        }
        $entitlementList = $samlAttributes[$this->entitlementAttribute];

        // we also add the entityID of the IdP to the "entitlement" to be able
        // to enforce which IdP issued the entitlement. This is useful in the
        // multi-IdP federation context where not every IdP is allowed to claim
        // every entitlement...
        $returnEntitlementList = [];
        foreach ($entitlementList as $e) {
            $returnEntitlementList[] = $e;
            $returnEntitlementList[] = sprintf('%s|%s', $idpEntityId, $e);
        }

        return $returnEntitlementList;
    }

    /**
     * @param \LetsConnect\Common\Http\Request $request
     * @param string|null                      $idpEntityId
     * @param array<string>                    $authnContext
     *
     * @return \LetsConnect\Common\Http\RedirectResponse
     */
    private static function getLoginRedirect(Request $request, $idpEntityId, array $authnContext)
    {
        $httpQuery = [
            'ReturnTo' => $request->getUri(),
        ];
        if (null !== $idpEntityId) {
            $httpQuery['IdP'] = $idpEntityId;
        }
        if (0 !== \count($authnContext)) {
            $httpQuery['AuthnContext'] = implode(',', $authnContext);
        }

        // redirect to SamlModule "login" endpoint
        return new RedirectResponse(
            sprintf(
                '%s_saml/login?%s',
                $request->getRootUri(),
                http_build_query($httpQuery)
            )
        );
    }
}
