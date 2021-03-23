<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateTime;
use fkooman\Otp\Exception\OtpException;
use fkooman\Otp\Totp;
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\InputValidation;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\Http\SessionInterface;
use LC\Common\TplInterface;

class TwoFactorEnrollModule implements ServiceModuleInterface
{
    /** @var \LC\Common\Http\SessionInterface */
    private $session;

    /** @var \LC\Common\TplInterface */
    private $tpl;

    /** @var Storage */
    private $storage;

    public function __construct(SessionInterface $session, TplInterface $tpl, Storage $storage)
    {
        $this->session = $session;
        $this->tpl = $tpl;
        $this->storage = $storage;
    }

    public function init(Service $service): void
    {
        $service->get(
            '/two_factor_enroll',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];
                $hasTotpSecret = false !== $this->storage->getOtpSecret($userInfo->getUserId());
                $totpSecret = Totp::generateSecret();
                $totp = new Totp($this->storage);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalEnrollTwoFactor',
                        [
                            'requireTwoFactorEnrollment' => null !== $this->session->get('_two_factor_enroll_redirect_to'),
                            'hasTotpSecret' => $hasTotpSecret,
                            'totpSecret' => $totpSecret,
                            'otpAuthUrl' => $totp->getEnrollmentUri($userInfo->getUserId(), $totpSecret, $request->getServerName()),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/two_factor_enroll',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $totpSecret = InputValidation::totpSecret($request->requirePostParameter('totp_secret'));
                $totpKey = InputValidation::totpKey($request->requirePostParameter('totp_key'));
                $redirectTo = $this->session->get('_two_factor_enroll_redirect_to');

                $totp = new Totp($this->storage);
                try {
                    $totp->register($userInfo->getUserId(), $totpSecret, $totpKey);
                    $this->storage->addUserMessage($userInfo->getUserId(), 'notification', 'TOTP secret registered', new DateTime());
                    if (null !== $redirectTo) {
                        $this->session->remove('_two_factor_enroll_redirect_to');

                        // mark as 2FA verified
                        $this->session->regenerate();
                        $this->session->set('_two_factor_verified', $userInfo->getUserId());

                        return new RedirectResponse($redirectTo);
                    }

                    return new RedirectResponse($request->getRootUri().'account', 302);
                } catch (OtpException $e) {
                    $msg = sprintf('TOTP registration failed: %s', $e->getMessage());
                    $this->storage->addUserMessage($userInfo->getUserId(), 'notification', $msg, new DateTime());

                    // we were unable to set the OTP secret
                    // XXX why is hasTotpSecret needed here?
                    $hasTotpSecret = false !== $this->storage->getOtpSecret($userInfo->getUserId());

                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalEnrollTwoFactor',
                            [
                                'requireTwoFactorEnrollment' => null !== $redirectTo,
                                'hasTotpSecret' => $hasTotpSecret,
                                'totpSecret' => $totpSecret,
                                'error_code' => 'invalid_otp_code',
                                'otpAuthUrl' => $totp->getEnrollmentUri($userInfo->getUserId(), $totpSecret, $request->getServerName()),
                            ]
                        )
                    );
                }
            }
        );
    }
}
