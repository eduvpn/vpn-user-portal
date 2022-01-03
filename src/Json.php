<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

class Json
{
    /**
     * @param mixed $jsonData
     */
    public static function encode($jsonData): string
    {
        return json_encode($jsonData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES, 32);
    }

    public static function decode(string $jsonString): array
    {
        return json_decode($jsonString, true, 32, JSON_THROW_ON_ERROR);
    }
}
