<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http\Auth;

use DateTimeImmutable;
use DateTimeZone;
use fkooman\SAML\SP\Api\AuthOptions;
use fkooman\SAML\SP\Api\SamlAuth;
use LC\Portal\Config;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\Http\RedirectResponse;
use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\UserInfo;

class PhpSamlSpAuthModule extends AbstractAuthModule
{
    private Config $config;

    private SamlAuth $samlAuth;

    private DateTimeImmutable $dateTime;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->samlAuth = new SamlAuth();
        $this->dateTime = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function userInfo(Request $request): ?UserInfo
    {
        $authOptions = $this->getAuthOptions();
        if (!$this->samlAuth->isAuthenticated($authOptions)) {
            return null;
        }

        $samlAssertion = $this->samlAuth->getAssertion($authOptions);
        // XXX verify AuthnContextClassRef?!
        $samlAttributes = $samlAssertion->getAttributes();
        $userIdAttribute = $this->config->requireString('userIdAttribute');
        if (!\array_key_exists($userIdAttribute, $samlAttributes)) {
            throw new HttpException(sprintf('missing SAML user_id attribute "%s"', $userIdAttribute), 500);
        }

        // we no longer need "samlAuth" afterwards, call its destructor to
        // clean up the SamlAuth session and get back our own...
        unset($this->samlAuth);

        $userId = $samlAttributes[$userIdAttribute][0];

        return new UserInfo(
            $userId,
            $this->getPermissionList($samlAttributes)
        );
    }

    public function startAuth(Request $request): ?Response
    {
        return new RedirectResponse($this->samlAuth->getLoginURL($this->getAuthOptions()));
    }

    public function triggerLogout(Request $request): Response
    {
        return new RedirectResponse(
            $request->getScheme().'://'.$request->getAuthority().'/php-saml-sp/logout?'.http_build_query(['ReturnTo' => $request->requireHeader('HTTP_REFERER')])
        );
    }

    private function getAuthOptions(): AuthOptions
    {
        $authOptions = new AuthOptions();
        if (null !== $authnContext = $this->config->optionalArray('authnContext')) {
            $authOptions->withAuthnContextClassRef($authnContext);
        }

        return $authOptions;
    }

    /**
     * @param array<string,array<string>> $samlAttributes
     *
     * @return array<string>
     */
    private function getPermissionList(array $samlAttributes): array
    {
        $permissionList = [];
        foreach ($this->config->requireArray('permissionAttributeList', []) as $permissionAttribute) {
            if (\array_key_exists($permissionAttribute, $samlAttributes)) {
                $permissionList = array_merge($permissionList, $samlAttributes[$permissionAttribute]);
            }
        }

        return $permissionList;
    }
}
