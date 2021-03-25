<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\Service;
use LC\Portal\Http\ServiceModuleInterface;

class QrModule implements ServiceModuleInterface
{
    const QR_ENCODE_PATH = '/usr/bin/qrencode';

    public function init(Service $service): void
    {
        $service->get(
            '/qr',
            function (Request $request, array $hookData): Response {
                $qrText = $request->requireQueryParameter('qr_text');

                $response = new Response(200, 'image/png');
                $response->setBody(self::generate($qrText));

                return $response;
            }
        );
    }

    private static function generate(string $qrText): string
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
