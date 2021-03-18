<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\SessionInterface;
use LC\Common\TplInterface;

class TwoFactorHook implements BeforeHookInterface
{
    /** @var Storage */
    private $storage;

    /** @var SessionInterface */
    private $session;

    /** @var \LC\Common\TplInterface */
    private $tpl;

    /** @var bool */
    private $requireTwoFactor;

    /**
     * @param bool $requireTwoFactor
     */
    public function __construct(Storage $storage, SessionInterface $session, TplInterface $tpl, $requireTwoFactor)
    {
        $this->storage = $storage;
        $this->session = $session;
        $this->tpl = $tpl;
        $this->requireTwoFactor = $requireTwoFactor;
    }

    /**
     * @return bool|\LC\Common\Http\Response
     */
    public function executeBefore(Request $request, array $hookData)
    {
        if (!\array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }

        // some URIs are allowed as they are used for either logging in, or
        // verifying the OTP key
        $whiteList = [
            'POST' => [
                '/two_factor_enroll',
                '/_form/auth/verify',
                '/_form/auth/logout',
                '/_logout',
                '/_two_factor/auth/verify/totp',
            ],
            'GET' => [
                '/two_factor_enroll',
                '/qr',
                '/documentation',
            ],
        ];
        if (Service::isWhitelisted($request, $whiteList)) {
            return false;
        }

        /** @var \LC\Common\Http\UserInfo */
        $userInfo = $hookData['auth'];
        if (null !== $twoFactorVerified = $this->session->get('_two_factor_verified')) {
            if ($userInfo->getUserId() !== $twoFactorVerified) {
                throw new HttpException('two-factor code not bound to authenticated user', 400);
            }

            return true;
        }

        // check if user is enrolled
        $hasTotpSecret = false !== $this->storage->getOtpSecret($userInfo->getUserId());
        if ($hasTotpSecret) {
            // user is enrolled for 2FA, ask for it!
            return new HtmlResponse(
                $this->tpl->render(
                    'twoFactorTotp',
                    [
                        '_two_factor_user_id' => $userInfo->getUserId(),
                        '_two_factor_auth_invalid' => false,
                        '_two_factor_auth_redirect_to' => $request->getUri(),
                    ]
                )
            );
        }

        if ($this->requireTwoFactor) {
            // 2FA required, but user not enrolled, offer them to enroll
            $this->session->set('_two_factor_enroll_redirect_to', $request->getUri());

            return new RedirectResponse($request->getRootUri().'two_factor_enroll');
        }

        // 2FA not required, and user not enrolled...
        $this->session->regenerate();
        $this->session->set('_two_factor_verified', $userInfo->getUserId());

        return true;
    }
}
