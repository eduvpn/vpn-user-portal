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
use Base32\Base32;
use Otp\GoogleAuthenticator;
use Otp\Otp;

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
                $otpSecret = GoogleAuthenticator::generateRandom();

                return new HtmlResponse(
                    $this->tpl->render('vpnPortalOtp', ['otpSecret' => $otpSecret])
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

                $otp = new Otp();
                if ($otp->checkTotp(Base32::decode($otpSecret), $otpKey)) {
                    // XXX we do not store this key in the log of used keys, so
                    // it could be replayed in the small window by connecting
                    // to the VPN with the same code
                    $this->serverClient->setOtpSecret($userId, $otpSecret);

                    return new RedirectResponse($request->getRootUri().'account', 302);
                }

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
}
