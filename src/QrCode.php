<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

class QrCode
{
    private const QR_ENCODE_PATH = '/usr/bin/qrencode';

    public static function generate(string $qrText): string
    {
        // XXX throw an exception when it is not possible to generate the QR
        // code because of too much data for example and handle it properly
        // in the portal...
        ob_start();
        passthru(
            sprintf(
                '%s -m 0 -s 5 -t PNG -o - -- %s',
                self::QR_ENCODE_PATH,
                escapeshellarg($qrText)
            )
        );

        return ob_get_clean();
    }
}
