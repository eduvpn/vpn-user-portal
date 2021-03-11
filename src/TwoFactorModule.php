<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\Otp\Exception\OtpException;
use fkooman\Otp\Totp;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\InputValidation;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\Http\SessionInterface;
use LC\Common\TplInterface;

class TwoFactorModule implements ServiceModuleInterface
{
    /** @var Storage */
    private $storage;

    /** @var SessionInterface */
    private $session;

    /** @var \LC\Common\TplInterface */
    private $tpl;

    public function __construct(Storage $storage, SessionInterface $session, TplInterface $tpl)
    {
        $this->storage = $storage;
        $this->session = $session;
        $this->tpl = $tpl;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->post(
            '/_two_factor/auth/verify/totp',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                if (!\array_key_exists('auth', $hookData)) {
                    throw new HttpException('authentication hook did not run before', 500);
                }
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $this->session->remove('_two_factor_verified');

                $totpKey = InputValidation::totpKey($request->requirePostParameter('_two_factor_auth_totp_key'));
                $redirectTo = $request->requirePostParameter('_two_factor_auth_redirect_to');

                try {
                    $totp = new Totp($this->storage);
                    $totp->verify($userInfo->getUserId(), $totpKey);
                    $this->session->regenerate();
                    $this->session->set('_two_factor_verified', $userInfo->getUserId());

                    return new RedirectResponse($redirectTo, 302);
                } catch (OtpException $e) {
                    // unable to validate the OTP
                    $msg = sprintf('TOTP validation failed: %s', $e->getMessage());
                    $this->storage->addUserMessage($userInfo->getUserId(), 'notification', $msg);

                    return new HtmlResponse(
                        $this->tpl->render(
                            'twoFactorTotp',
                            [
                                '_two_factor_user_id' => $userInfo->getUserId(),
                                '_two_factor_auth_invalid' => true,
                                '_two_factor_auth_error_msg' => $msg,
                                '_two_factor_auth_redirect_to' => $redirectTo,
                            ]
                        )
                    );
                }
            }
        );
    }
}
