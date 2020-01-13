<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

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

    public function __construct(Config $config, SeSamlSession $session)
    {
        $this->config = $config;
        $this->samlAuth = new SamlAuth($session);
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

        $authInfo = $this->samlAuth->requireAuth();
        if (\is_string($authInfo)) {
            return new RedirectResponse($authInfo);
        }

        $samlAttributes = $authInfo->getAttributes();
        /** @var string $userIdAttribute */
        $userIdAttribute = $this->config->getItem('userIdAttribute');
        if (!\array_key_exists($userIdAttribute, $samlAttributes)) {
            throw new HttpException(sprintf('missing SAML user_id attribute "%s"', $userIdAttribute), 500);
        }
        $userId = $samlAttributes[$userIdAttribute][0];

        $userInfo = new UserInfo(
            $userId,
            [],
        );

        return $userInfo;
    }
}
