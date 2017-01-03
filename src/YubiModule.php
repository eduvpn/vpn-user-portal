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

use SURFnet\VPN\Common\Http\HtmlResponse;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\HttpClient\Exception\ApiException;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\TplInterface;

class YubiModule implements ServiceModuleInterface
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
            '/yubi',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $hasYubiId = $this->serverClient->get('has_yubi_key_id', ['user_id' => $userId]);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalYubi',
                        [
                            'hasYubiId' => $hasYubiId,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/yubi',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $yubiKeyOtp = InputValidation::yubiKeyOtp($request->getPostParameter('yubi_key_otp'));

                try {
                    $this->serverClient->post('set_yubi_key_id', ['user_id' => $userId, 'yubi_key_otp' => $yubiKeyOtp]);
                } catch (ApiException $e) {
                    // we were unable to set the Yubi ID
                    $hasYubiId = $this->serverClient->get('has_yubi_key_id', ['user_id' => $userId]);

                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalYubi',
                            [
                                'hasYubiId' => $hasYubiId,
                                'error_code' => 'invalid_yubi_key_otp',
                            ]
                        )
                    );
                }

                return new RedirectResponse($request->getRootUri().'account', 302);
            }
        );
    }
}
