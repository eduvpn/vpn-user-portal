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
    private $permissionAttribute;

    /** @var array<string> */
    private $authnContext;

    /** @var array<string,array<string>> */
    private $permissionAuthnContext;

    /**
     * @param \fkooman\SAML\SP\SP         $samlSp
     * @param string|null                 $idpEntityId
     * @param string                      $userIdAttribute
     * @param string|null                 $permissionAttribute
     * @param array<string>               $authnContext
     * @param array<string,array<string>> $permissionAuthnContext
     */
    public function __construct(SP $samlSp, $idpEntityId, $userIdAttribute, $permissionAttribute, array $authnContext, array $permissionAuthnContext)
    {
        $this->samlSp = $samlSp;
        $this->idpEntityId = $idpEntityId;
        $this->userIdAttribute = $userIdAttribute;
        $this->permissionAttribute = $permissionAttribute;
        $this->authnContext = $authnContext;
        $this->permissionAuthnContext = $permissionAuthnContext;
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
            return self::getLoginRedirect($request, $this->authnContext);
        }

        $samlAttributes = $samlAssertion->getAttributes();

        if (!\array_key_exists($this->userIdAttribute, $samlAttributes)) {
            throw new HttpException(sprintf('missing SAML user_id attribute "%s"', $this->userIdAttribute), 500);
        }

        $userId = $samlAttributes[$this->userIdAttribute][0];

        $userAuthnContext = $samlAssertion->getAuthnContext();
        if (null !== $this->permissionAttribute) {
            $userPermissions = [];
            if (\array_key_exists($this->permissionAttribute, $samlAttributes)) {
                $userPermissions = $samlAttributes[$this->permissionAttribute];
            }

            // if we got a permission that's part of the
            // permissionAuthnContext we have to make sure we have one of
            // the listed AuthnContexts
            foreach ($this->permissionAuthnContext as $permission => $authnContext) {
                if (\in_array($permission, $userPermissions, true)) {
                    if (!\in_array($userAuthnContext, $authnContext, true)) {
                        // we do not have the required AuthnContext, trigger login
                        // and request the first acceptable AuthnContext
                        return self::getLoginRedirect($request, $authnContext);
                    }
                }
            }
        }

        return new UserInfo(
            $userId,
            $this->getPermissionList($samlAttributes),
            $samlAssertion->getAuthnInstant()
        );
    }

    /**
     * @param array<string,array<string>> $samlAttributes
     *
     * @return array<string>
     */
    private function getPermissionList(array $samlAttributes)
    {
        if (null === $this->permissionAttribute) {
            return [];
        }
        if (!\array_key_exists($this->permissionAttribute, $samlAttributes)) {
            return [];
        }

        return $samlAttributes[$this->permissionAttribute];
    }

    /**
     * @param \LetsConnect\Common\Http\Request $request
     * @param array<string>                    $authnContext
     *
     * @return \LetsConnect\Common\Http\RedirectResponse
     */
    private function getLoginRedirect(Request $request, array $authnContext)
    {
        $httpQuery = [
            'ReturnTo' => $request->getUri(),
        ];
        if (null !== $this->idpEntityId) {
            $httpQuery['IdP'] = $this->idpEntityId;
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
