<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use BaconQrCode\Renderer\Image\Png;
use BaconQrCode\Writer;
use fkooman\SeCookie\SessionInterface;
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\InputValidation;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\HttpClient\Exception\ApiException;
use LC\Common\HttpClient\ServerClient;
use LC\Common\TplInterface;
use ParagonIE\ConstantTime\Base32;

class TwoFactorEnrollModule implements ServiceModuleInterface
{
    /** @var array<string> */
    private $twoFactorMethods;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \LC\Common\TplInterface */
    private $tpl;

    /** @var \LC\Common\HttpClient\ServerClient */
    private $serverClient;

    /**
     * @param array<string>                      $twoFactorMethods
     * @param \fkooman\SeCookie\SessionInterface $session
     * @param \LC\Common\TplInterface            $tpl
     * @param \LC\Common\HttpClient\ServerClient $serverClient
     */
    public function __construct(array $twoFactorMethods, SessionInterface $session, TplInterface $tpl, ServerClient $serverClient)
    {
        $this->twoFactorMethods = $twoFactorMethods;
        $this->session = $session;
        $this->tpl = $tpl;
        $this->serverClient = $serverClient;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/two_factor_enroll',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];
                $hasTotpSecret = $this->serverClient->get('has_totp_secret', ['user_id' => $userInfo->getUserId()]);

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
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $totpSecret = InputValidation::totpSecret($request->getPostParameter('totp_secret'));
                $totpKey = InputValidation::totpKey($request->getPostParameter('totp_key'));
                $hasTwoFactorEnrollRedirectTo = $this->session->has('_two_factor_enroll_redirect_to');

                try {
                    $this->serverClient->post('set_totp_secret', ['user_id' => $userInfo->getUserId(), 'totp_secret' => $totpSecret, 'totp_key' => $totpKey]);
                } catch (ApiException $e) {
                    // we were unable to set the OTP secret
                    $hasTotpSecret = $this->serverClient->get('has_totp_secret', ['user_id' => $userInfo->getUserId()]);

                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalEnrollTwoFactor',
                            [
                                'requireTwoFactorEnrollment' => $hasTwoFactorEnrollRedirectTo,
                                'twoFactorMethods' => $this->twoFactorMethods,
                                'hasTotpSecret' => $hasTotpSecret,
                                'totpSecret' => $totpSecret,
                                'error_code' => 'invalid_otp_code',
                            ]
                        )
                    );
                }

                if ($hasTwoFactorEnrollRedirectTo) {
                    $twoFactorEnrollRedirectTo = $this->session->get('_two_factor_enroll_redirect_to');
                    $this->session->delete('_two_factor_enroll_redirect_to');

                    // mark as 2FA verified
                    $this->session->regenerate(true);
                    $this->session->set('_two_factor_verified', $userInfo->getUserId());

                    return new RedirectResponse($twoFactorEnrollRedirectTo);
                }

                return new RedirectResponse($request->getRootUri().'account', 302);
            }
        );

        $service->get(
            '/two_factor_enroll_qr',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $totpSecret = InputValidation::totpSecret($request->getQueryParameter('totp_secret'));

                $otpAuthUrl = sprintf(
                    'otpauth://totp/%s:%s?secret=%s&issuer=%s',
                    $request->getServerName(),
                    urlencode($userInfo->getUserId()),
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
