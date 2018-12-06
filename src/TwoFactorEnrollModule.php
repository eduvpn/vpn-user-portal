<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use BaconQrCode\Renderer\Image\Png;
use BaconQrCode\Writer;
use ParagonIE\ConstantTime\Base32;
use SURFnet\VPN\Common\Http\HtmlResponse;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\HttpClient\Exception\ApiException;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\TplInterface;

class TwoFactorEnrollModule implements ServiceModuleInterface
{
    /** @var array<string> */
    private $twoFactorMethods;

    /** @var \SURFnet\VPN\Common\TplInterface */
    private $tpl;

    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /**
     * @param array<string>                               $twoFactorMethods
     * @param \SURFnet\VPN\Common\TplInterface            $tpl
     * @param \SURFnet\VPN\Common\HttpClient\ServerClient $serverClient
     */
    public function __construct(array $twoFactorMethods, TplInterface $tpl, ServerClient $serverClient)
    {
        $this->twoFactorMethods = $twoFactorMethods;
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
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];
                $hasTotpSecret = $this->serverClient->get('has_totp_secret', ['user_id' => $userInfo->id()]);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalEnrollTwoFactor',
                        [
                            'twoFactorMethods' => $this->twoFactorMethods,
                            'hasTotpSecret' => $hasTotpSecret,
                            'totpSecret' => Base32::encodeUpper(random_bytes(20)),
                        ]
                    )
                );
            }
        );

        if (\in_array('totp', $this->twoFactorMethods, true)) {
            $service->post(
                '/totp',
                /**
                 * @return \SURFnet\VPN\Common\Http\Response
                 */
                function (Request $request, array $hookData) {
                    $userInfo = $hookData['auth'];

                    $totpSecret = InputValidation::totpSecret($request->getPostParameter('totp_secret'));
                    $totpKey = InputValidation::totpKey($request->getPostParameter('totp_key'));

                    try {
                        $this->serverClient->post('set_totp_secret', ['user_id' => $userInfo->id(), 'totp_secret' => $totpSecret, 'totp_key' => $totpKey]);
                    } catch (ApiException $e) {
                        // we were unable to set the OTP secret
                        $hasTotpSecret = $this->serverClient->get('has_totp_secret', ['user_id' => $userInfo->id()]);

                        return new HtmlResponse(
                            $this->tpl->render(
                                'vpnPortalEnrollTwoFactor',
                                [
                                    'hasTotpSecret' => $hasTotpSecret,
                                    'totpSecret' => $totpSecret,
                                    'error_code' => 'invalid_otp_code',
                                ]
                            )
                        );
                    }

                    return new RedirectResponse($request->getRootUri().'account', 302);
                }
            );

            $service->get(
                '/totp/qr-code',
                /**
                 * @return \SURFnet\VPN\Common\Http\Response
                 */
                function (Request $request, array $hookData) {
                    $userInfo = $hookData['auth'];

                    $totpSecret = InputValidation::totpSecret($request->getQueryParameter('totp_secret'));

                    $otpAuthUrl = sprintf(
                        'otpauth://totp/%s:%s?secret=%s&issuer=%s',
                        $request->getServerName(),
                        $userInfo->id(),
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
}
