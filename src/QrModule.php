<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;

class QrModule implements ServiceModuleInterface
{
    const QR_ENCODE_PATH = '/usr/bin/qrencode';

    public function init(Service $service): void
    {
        $service->get(
            '/qr',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $qrText = $request->requireQueryParameter('qr_text');

                $response = new Response(200, 'image/png');
                $response->setBody(self::generate($qrText));

                return $response;
            }
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
