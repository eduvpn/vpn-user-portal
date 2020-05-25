<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateTime;
use fkooman\SAML\SP\Api\AuthOptions;
use fkooman\SAML\SP\Api\SamlAuth;
use LC\Common\Config;
use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\UserInfo;

class PhpSamlSpAuthentication implements BeforeHookInterface
{
    /** @var \LC\Common\Config */
    private $config;

    /** @var \fkooman\SAML\SP\Api\SamlAuth */
    private $samlAuth;

    /** @var \DateTime */
    private $dateTime;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->samlAuth = new SamlAuth();
        $this->dateTime = new DateTime();
    }

    /**
     * @return false|\LC\Common\Http\RedirectResponse|\LC\Common\Http\UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
    {
        $whiteList = [
            'POST' => [
                '/_logout',
            ],
        ];
        if (Service::isWhitelisted($request, $whiteList)) {
            return false;
        }

        $authOptions = new AuthOptions();
        if (null !== $authnContext = $this->config->optionalItem('authnContext')) {
            $authOptions->withAuthnContextClassRef($authnContext);
        }
        if (!$this->samlAuth->isAuthenticated($authOptions)) {
            return new RedirectResponse($this->samlAuth->getLoginURL($authOptions));
        }

        // user is authenticated, get the assertion, make sure the authOptions,
        // i.e. the AuthnContextClassRef are verified here...
        $samlAssertion = $this->samlAuth->getAssertion($authOptions);
        $samlAttributes = $samlAssertion->getAttributes();
        /** @var string $userIdAttribute */
        $userIdAttribute = $this->config->getItem('userIdAttribute');
        if (!\array_key_exists($userIdAttribute, $samlAttributes)) {
            throw new HttpException(sprintf('missing SAML user_id attribute "%s"', $userIdAttribute), 500);
        }
        $userId = $samlAttributes[$userIdAttribute][0];
        $userAuthnContext = $samlAssertion->getAuthnContext();
        if (0 !== \count($this->getPermissionAttributeList())) {
            $userPermissions = $this->getPermissionList($samlAttributes);

            /** @var array<string,string>|null $permissionAuthnContext */
            if (null === $permissionAuthnContext = $this->config->optionalItem('permissionAuthnContext')) {
                $permissionAuthnContext = [];
            }

            // if we got a permission that's part of the
            // permissionAuthnContext we have to make sure we have one of
            // the listed AuthnContexts
            foreach ($permissionAuthnContext as $permission => $authnContext) {
                if (\in_array($permission, $userPermissions, true)) {
                    if (!\in_array($userAuthnContext, $authnContext, true)) {
                        // we need another AuthnContextClassRef, but we DO want
                        // to use the same IdP as before to skip the WAYF
                        // (if any)
                        return new RedirectResponse(
                            $this->samlAuth->getLoginURL(
                                $authOptions->withAuthnContextClassRef($authnContext)->withIdp($samlAssertion->getIssuer())
                            )
                        );
                    }
                }
            }
        }

        $userInfo = new UserInfo(
            $userId,
            $this->getPermissionList($samlAttributes)
        );

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
        foreach ($this->getPermissionAttributeList() as $permissionAttribute) {
            if (\array_key_exists($permissionAttribute, $samlAttributes)) {
                $permissionList = array_merge($permissionList, $samlAttributes[$permissionAttribute]);
            }
        }

        return $permissionList;
    }

    /**
     * @return array<string>
     */
    private function getPermissionAttributeList()
    {
        /** @var array<string>|string|null */
        $permissionAttributeList = $this->config->optionalItem('permissionAttribute');
        if (\is_string($permissionAttributeList)) {
            $permissionAttributeList = [$permissionAttributeList];
        }
        if (null === $permissionAttributeList) {
            $permissionAttributeList = [];
        }

        return $permissionAttributeList;
    }
}
