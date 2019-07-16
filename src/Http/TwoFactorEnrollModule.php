<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use BaconQrCode\Renderer\Image\Png;
use BaconQrCode\Writer;
use fkooman\Otp\Exception\OtpException;
use fkooman\Otp\Totp;
use fkooman\SeCookie\SessionInterface;
use LC\Portal\Storage;
use LC\Portal\TplInterface;
use ParagonIE\ConstantTime\Base32;

class TwoFactorEnrollModule implements ServiceModuleInterface
{
    /** @var \LC\Portal\Storage */
    private $storage;

    /** @var array<string> */
    private $twoFactorMethods;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \LC\Portal\TplInterface */
    private $tpl;

    /**
     * @param array<string> $twoFactorMethods
     */
    public function __construct(Storage $storage, array $twoFactorMethods, SessionInterface $session, TplInterface $tpl)
    {
        $this->storage = $storage;
        $this->twoFactorMethods = $twoFactorMethods;
        $this->session = $session;
        $this->tpl = $tpl;
    }

    public function init(Service $service): void
    {
        $service->get(
            '/two_factor_enroll',
            function (Request $request, array $hookData): Response {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];
                $hasTotpSecret = false !== $this->storage->getOtpSecret($userInfo->getUserId());

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalEnrollTwoFactor',
                        [
                            'requireTwoFactorEnrollment' => $this->session->has('_two_factor_enroll_redirect_to'),
                            'twoFactorMethods' => $this->twoFactorMethods,
                            'hasTotpSecret' => $hasTotpSecret,
                            'totpSecret' => Base32::encodeUpper(random_bytes(20)),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/two_factor_enroll',
            function (Request $request, array $hookData): Response {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];
                $userId = $userInfo->getUserId();

                $totpSecret = InputValidation::totpSecret($request->requirePostParameter('totp_secret'));
                $totpKey = InputValidation::totpKey($request->requirePostParameter('totp_key'));
                $hasTwoFactorEnrollRedirectTo = $this->session->has('_two_factor_enroll_redirect_to');

                try {
                    $totp = new Totp($this->storage);
                    $totp->register($userId, $totpSecret, $totpKey);
                } catch (OtpException $e) {
                    $hasTotpSecret = false !== $this->storage->getOtpSecret($userId);

                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalEnrollTwoFactor',
                            [
                                'requireTwoFactorEnrollment' => $hasTwoFactorEnrollRedirectTo,
                                'twoFactorMethods' => $this->twoFactorMethods,
                                'hasTotpSecret' => $hasTotpSecret,
                                'totpSecret' => $totpSecret,
                                // XXX the error can be more specific here from the OtpException!
                                'error_code' => 'invalid_otp_code',
                            ]
                        )
                    );
                }

                if ($hasTwoFactorEnrollRedirectTo) {
                    $twoFactorEnrollRedirectTo = $this->session->get('_two_factor_enroll_redirect_to');
                    $this->session->delete('_two_factor_enroll_redirect_to');

                    // mark as 2FA verified
                    $this->session->regenerate();
                    $this->session->set('_two_factor_verified', $userId);

                    return new RedirectResponse($twoFactorEnrollRedirectTo);
                }

                return new RedirectResponse($request->getRootUri().'account', 302);
            }
        );

        $service->get(
            '/two_factor_enroll_qr',
            function (Request $request, array $hookData): Response {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $totpSecret = InputValidation::totpSecret($request->requireQueryParameter('totp_secret'));

                $otpAuthUrl = sprintf(
                    'otpauth://totp/%s:%s?secret=%s&issuer=%s',
                    $request->getServerName(),
                    $userInfo->getUserId(),
                    $totpSecret,
                    $request->getServerName()
                );

                $renderer = new Png();
                $renderer->setHeight(256);
                $renderer->setWidth(256);
                $writer = new Writer($renderer);
                $qrCode = $writer->writeString($otpAuthUrl);

                $response = new Response(200, 'image/png');
                $response->setBody($qrCode);

                return $response;
            }
        );
    }
}
