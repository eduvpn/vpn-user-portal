<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateInterval;
use DateTime;
use fkooman\SAML\SP\SP;
use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\UserInfo;

class SamlAuthenticationHook implements BeforeHookInterface
{
    /** @var \fkooman\SAML\SP\SP */
    private $samlSp;

    /** @var string|null */
    private $idpEntityId;

    /** @var string */
    private $userIdAttribute;

    /** @var array<string> */
    private $permissionAttributeList;

    /** @var array<string> */
    private $authnContext;

    /** @var array<string,array<string>> */
    private $permissionAuthnContext;

    /** @var array<string,string> */
    private $permissionSessionExpiry;

    /** @var \DateTime */
    private $dateTime;

    /**
     * @param string|null                 $idpEntityId
     * @param string                      $userIdAttribute
     * @param array<string>               $permissionAttributeList
     * @param array<string>               $authnContext
     * @param array<string,array<string>> $permissionAuthnContext
     * @param array<string,string>        $permissionSessionExpiry
     */
    public function __construct(SP $samlSp, $idpEntityId, $userIdAttribute, array $permissionAttributeList, array $authnContext, array $permissionAuthnContext, array $permissionSessionExpiry)
    {
        $this->samlSp = $samlSp;
        $this->idpEntityId = $idpEntityId;
        $this->userIdAttribute = $userIdAttribute;
        $this->permissionAttributeList = $permissionAttributeList;
        $this->authnContext = $authnContext;
        $this->permissionAuthnContext = $permissionAuthnContext;
        $this->permissionSessionExpiry = $permissionSessionExpiry;
        $this->dateTime = new DateTime();
    }

    /**
     * @return false|\LC\Common\Http\RedirectResponse|\LC\Common\Http\UserInfo
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

        if (!$this->samlSp->hasAssertion()) {
            // user not (yet) authenticated, redirect to "login" endpoint
            return self::getLoginRedirect($request, $this->authnContext);
        }

        $samlAssertion = $this->samlSp->getAssertion();
        $samlAttributes = $samlAssertion->getAttributes();

        if (!\array_key_exists($this->userIdAttribute, $samlAttributes)) {
            throw new HttpException(sprintf('missing SAML user_id attribute "%s"', $this->userIdAttribute), 500);
        }

        $userId = $samlAttributes[$this->userIdAttribute][0];
        $sessionExpiresAt = null;

        $userAuthnContext = $samlAssertion->getAuthnContext();
        if (0 !== \count($this->permissionAttributeList)) {
            $userPermissions = $this->getPermissionList($samlAttributes);

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

            // if we got a permission that's part of the
            // permissionSessionExpiry we use it to determine the time we want
            // the session to expire
            $minSessionExpiresAt = null;
            foreach ($this->permissionSessionExpiry as $permission => $sessionExpiry) {
                if (\in_array($permission, $userPermissions, true)) {
                    // make sure we take the sessionExpiresAt belonging to the
                    // permission that has the shortest expiry configured
                    $tmpSessionExpiresAt = date_add(clone $this->dateTime, new DateInterval($sessionExpiry));
                    if (null === $minSessionExpiresAt) {
                        $minSessionExpiresAt = $tmpSessionExpiresAt;
                    }
                    if ($tmpSessionExpiresAt < $minSessionExpiresAt) {
                        $minSessionExpiresAt = $tmpSessionExpiresAt;
                    }
                }
            }

            // take the minimum
            $sessionExpiresAt = $minSessionExpiresAt;
        }

        $userInfo = new UserInfo(
            $userId,
            $this->getPermissionList($samlAttributes)
        );

        if (null !== $sessionExpiresAt) {
            $userInfo->setSessionExpiresAt($sessionExpiresAt);
        }

        return $userInfo;
    }

    /**
     * @param array<string,array<string>> $samlAttributes
     *
     * @return array<string>
     */
    private function getPermissionList(array $samlAttributes)
    {
        $permissionList = [];
        foreach ($this->permissionAttributeList as $permissionAttribute) {
            if (\array_key_exists($permissionAttribute, $samlAttributes)) {
                $permissionList = array_merge($permissionList, $samlAttributes[$permissionAttribute]);
            }
        }

        return $permissionList;
    }

    /**
     * @param array<string> $authnContext
     *
     * @return \LC\Common\Http\RedirectResponse
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
