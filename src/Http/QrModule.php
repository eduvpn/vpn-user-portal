<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

class QrModule implements ServiceModuleInterface
{
    public const QR_ENCODE_PATH = '/usr/bin/qrencode';

    public function init(ServiceInterface $service): void
    {
        $service->get(
            '/qr',
            function (UserInfo $userInfo, Request $request): Response {
                // XXX we do NOT validate the string here, probably should!
                $qrText = $request->requireQueryParameter('qr_text', null);

                return new Response(self::generate($qrText), ['Content-Type' => 'image/png']);
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
