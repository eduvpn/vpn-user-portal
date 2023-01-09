<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use fkooman\SAML\SP\Api\AuthOptions;
use fkooman\SAML\SP\Api\SamlAuth;
use Vpn\Portal\Cfg\PhpSamlSpAuthConfig;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\Http\RedirectResponse;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Response;
use Vpn\Portal\Http\UserInfo;

class PhpSamlSpAuthModule extends AbstractAuthModule
{
    private PhpSamlSpAuthConfig $config;
    private SamlAuth $samlAuth;

    public function __construct(PhpSamlSpAuthConfig $config)
    {
        $this->config = $config;
        $this->samlAuth = new SamlAuth();
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
        $userIdAttribute = $this->config->userIdAttribute();
        if (!\array_key_exists($userIdAttribute, $samlAttributes)) {
            throw new HttpException(sprintf('missing SAML user_id attribute "%s"', $userIdAttribute), 500);
        }

        return new UserInfo(
            $samlAttributes[$userIdAttribute][0],
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
            $request->getScheme().'://'.$request->getAuthority().'/php-saml-sp/logout?'.http_build_query(['ReturnTo' => $request->requireReferrer()])
        );
    }

    private function getAuthOptions(): AuthOptions
    {
        $authOptions = new AuthOptions();
        if (null !== $authnContext = $this->config->authnContext()) {
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
        foreach ($this->config->permissionAttributeList() as $permissionAttribute) {
            if (\array_key_exists($permissionAttribute, $samlAttributes)) {
                $permissionList = array_merge($permissionList, $samlAttributes[$permissionAttribute]);
            }
        }

        return $permissionList;
    }
}
