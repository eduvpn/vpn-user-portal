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
use fkooman\SeCookie\SessionInterface;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\InputValidation;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\Http\UserInfo;
use LC\Common\TplInterface;

class TwoFactorModule implements ServiceModuleInterface
{
    /** @var Storage */
    private $storage;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \LC\Common\TplInterface */
    private $tpl;

    /**
     * @param Storage                            $storage
     * @param \fkooman\SeCookie\SessionInterface $session
     * @param \LC\Common\TplInterface            $tpl
     */
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
             * @return Response
             */
            function (Request $request, array $hookData) {
                if (!\array_key_exists('auth', $hookData)) {
                    throw new HttpException('authentication hook did not run before', 500);
                }
                /** @var UserInfo */
                $userInfo = $hookData['auth'];
                $userId = $userInfo->getUserId();

                $this->session->delete('_two_factor_verified');

                $totpKey = InputValidation::totpKey($request->getPostParameter('_two_factor_auth_totp_key'));
                $redirectTo = $request->getPostParameter('_two_factor_auth_redirect_to');

                try {
                    $totp = new Totp($this->storage);
                    $totp->verify($userId, $totpKey);
                    $this->session->regenerate(true);
                    $this->session->set('_two_factor_verified', $userId);

                    return new RedirectResponse($redirectTo, 302);
                } catch (OtpException $e) {
                    $this->storage->addUserMessage($userId, 'notification', 'OTP validation failed: '.$e->getMessage());

                    // unable to validate the OTP
                    return new HtmlResponse(
                        $this->tpl->render(
                            'twoFactorTotp',
                            [
                                '_two_factor_user_id' => $userId,
                                '_two_factor_auth_invalid' => true,
                                '_two_factor_auth_error_msg' => $e->getMessage(),
                                '_two_factor_auth_redirect_to' => $redirectTo,
                            ]
                        )
                    );
                }
            }
        );
    }
}
