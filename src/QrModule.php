<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\InputValidation;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\Http\UserInfo;

class QrModule implements ServiceModuleInterface
{
    const QR_ENCODE_PATH = '/usr/bin/qrencode';

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/qr/totp',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];
                $totpSecret = InputValidation::totpSecret($request->requireQueryParameter('secret'));
                $response = new Response(200, 'image/png');
                $response->setBody(self::generate(self::getOtpAuthUrl($request, $userInfo, $totpSecret)));

                return $response;
            }
        );
    }

    /**
     * @param string $labelStr
     *
     * @return string
     */
    private static function labelEncode($labelStr)
    {
        return rawurlencode(str_replace(':', '_', $labelStr));
    }

    /**
     * @param string $totpSecret
     *
     * @return string
     */
    private static function getOtpAuthUrl(Request $request, UserInfo $userInfo, $totpSecret)
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            self::labelEncode($request->getServerName()),
            self::labelEncode($userInfo->getUserId()),
            $totpSecret,
            self::labelEncode($request->getServerName())
        );
    }

    /**
     * @param string $qrText
     *
     * @return string
     */
    private static function generate($qrText)
    {
        ob_start();
        passthru(
            sprintf(
                '%s -m 0 -s 5 -t PNG -o - %s',
                self::QR_ENCODE_PATH,
                escapeshellarg($qrText)
            )
        );

        return ob_get_clean();
    }
}
