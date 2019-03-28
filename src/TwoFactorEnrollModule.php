<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use BaconQrCode\Renderer\Image\Png;
use BaconQrCode\Writer;
use fkooman\SeCookie\SessionInterface;
use LetsConnect\Common\Http\HtmlResponse;
use LetsConnect\Common\Http\InputValidation;
use LetsConnect\Common\Http\RedirectResponse;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Response;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\Http\ServiceModuleInterface;
use LetsConnect\Common\HttpClient\Exception\ApiException;
use LetsConnect\Common\HttpClient\ServerClient;
use LetsConnect\Common\TplInterface;
use ParagonIE\ConstantTime\Base32;

class TwoFactorEnrollModule implements ServiceModuleInterface
{
    /** @var array<string> */
    private $twoFactorMethods;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \LetsConnect\Common\TplInterface */
    private $tpl;

    /** @var \LetsConnect\Common\HttpClient\ServerClient */
    private $serverClient;

    /**
     * @param array<string>                               $twoFactorMethods
     * @param \fkooman\SeCookie\SessionInterface          $session
     * @param \LetsConnect\Common\TplInterface            $tpl
     * @param \LetsConnect\Common\HttpClient\ServerClient $serverClient
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
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LetsConnect\Common\Http\UserInfo */
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
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LetsConnect\Common\Http\UserInfo */
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
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LetsConnect\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $totpSecret = InputValidation::totpSecret($request->getQueryParameter('totp_secret'));

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
