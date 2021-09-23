<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\HttpClient;

trait HttpClientTrait
{
    /**
     * Properly encode HTTP (POST) query parameters while also supporting
     * duplicate key names. PHP's built in http_build_query uses weird key[]
     * syntax that I am not a big fan of.
     *
     * @param array<string,array<string>|string> $postParameters
     */
    private static function buildQuery(array $postParameters): string
    {
        $qParts = [];
        foreach ($postParameters as $k => $v) {
            if (\is_string($v)) {
                $qParts[] = urlencode($k).'='.urlencode($v);
            }
            if (\is_array($v)) {
                foreach ($v as $w) {
                    $qParts[] = urlencode($k).'='.urlencode($w);
                }
            }
        }

        return implode('&', $qParts);
    }
}
