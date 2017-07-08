<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
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
