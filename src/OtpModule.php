<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SURFnet\VPN\Portal;

use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\HtmlResponse;
use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\TplInterface;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Http\Response;
use BaconQrCode\Renderer\Image\Png;
use BaconQrCode\Writer;

class OtpModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\TplInterface */
    private $tpl;

    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    public function __construct(TplInterface $tpl, ServerClient $serverClient)
    {
        $this->tpl = $tpl;
        $this->serverClient = $serverClient;
    }

    public function init(Service $service)
    {
        $service->get(
            '/otp',
            function () {
                return new HtmlResponse(
                    $this->tpl->render('vpnPortalOtp', ['otpSecret' => self::generateSecret()])
                );
            }
        );

        $service->post(
            '/otp',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $otpSecret = $request->getPostParameter('otp_secret');
                InputValidation::otpSecret($otpSecret);
                $otpKey = $request->getPostParameter('otp_key');
                InputValidation::otpKey($otpKey);

                if (false === $this->serverClient->setTotpSecret($userId, $otpSecret, $otpKey)) {
                    // we were unable to set
                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalOtp',
                            [
                                'otpSecret' => $otpSecret,
                                'error_code' => 'invalid_otp_code',
                            ]
                        )
                    );
                }

                return new RedirectResponse($request->getRootUri().'account', 302);
            }
        );

        $service->get(
            '/otp-qr-code',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $otpSecret = $request->getQueryParameter('otp_secret');
                InputValidation::otpSecret($otpSecret);

                $otpAuthUrl = sprintf(
                    'otpauth://totp/%s:%s?secret=%s&issuer=%s',
                    $request->getServerName(),
                    $userId,
                    $otpSecret,
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

    private static function generateSecret()
    {
        // Insipired by https://github.com/ChristianRiesen/otp and modified a
        // bit, MIT licensed code
        // Generates a random BASE32 string of length 16 without padding
        $keys = array_merge(
            range('A', 'Z'),
            range(2, 7)
        );

        $otpSecret = '';
        for ($i = 0; $i < 16; ++$i) {
            $otpSecret .= $keys[random_int(0, 31)];
        }

        return $otpSecret;
    }
}
