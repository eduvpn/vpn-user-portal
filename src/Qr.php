<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use RuntimeException;

class Qr
{
    const QR_ENCODE_PATH = '/usr/bin/qrencode';

    /**
     * @param string $qrText
     *
     * @return string
     */
    public static function generate($qrText)
    {
        ob_start();
        passthru(
            sprintf(
                '%s -m 0 -s 5 -t SVG -o - %s',
                self::QR_ENCODE_PATH,
                escapeshellarg($qrText)
            )
        );

        // we only want the <svg></svg> part of the output
        if (1 !== preg_match('|(<svg.*</svg>)|sm', ob_get_clean(), $m)) {
            throw new RuntimeException('unable to get SVG encoded QR code');
        }

        return $m[1];
    }
}
