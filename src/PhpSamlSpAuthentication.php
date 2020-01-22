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

        if (null === $authnContext = $this->config->optionalItem('authnContext')) {
            $authnContext = [];
        }
        $authOptions->setAuthnContextClassRef($authnContext);

        if (!$this->samlAuth->isAuthenticated()) {
            return new RedirectResponse($this->samlAuth->getLoginURL($authOptions));
        }
        $samlAssertion = $this->samlAuth->getAssertion();
        $samlAttributes = $samlAssertion->getAttributes();
        /** @var string $userIdAttribute */
        $userIdAttribute = $this->config->getItem('userIdAttribute');
        if (!\array_key_exists($userIdAttribute, $samlAttributes)) {
            throw new HttpException(sprintf('missing SAML user_id attribute "%s"', $userIdAttribute), 500);
        }
        $userId = $samlAttributes[$userIdAttribute][0];
        $sessionExpiresAt = null;
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
                        // we do not have the required AuthnContext, trigger login
                        // and request the first acceptable AuthnContext
                        $authOptions->setAuthnContextClassRef($authnContext);

                        return new RedirectResponse($this->samlAuth->getLoginURL($authOptions));
                    }
                }
            }

            // if we got a permission that's part of the
            // permissionSessionExpiry we use it to determine the time we want
            // the session to expire
            $minSessionExpiresAt = null;

            /* @var array<string,string>|null */
            if (null === $permissionSessionExpiry = $this->config->optionalItem('permissionSessionExpiry')) {
                $permissionSessionExpiry = [];
            }

            foreach ($permissionSessionExpiry as $permission => $sessionExpiry) {
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
