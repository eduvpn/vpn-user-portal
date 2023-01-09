<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

class Json
{
    private const ENCODE_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES;
    private const DECODE_FLAGS = JSON_THROW_ON_ERROR;
    private const DEPTH = 32;

    /**
     * @param mixed $jsonData
     */
    public static function encode($jsonData): string
    {
        return json_encode($jsonData, self::ENCODE_FLAGS, self::DEPTH);
    }

    /**
     * @param mixed $jsonData
     */
    public static function encodePretty($jsonData): string
    {
        return json_encode($jsonData, self::ENCODE_FLAGS | JSON_PRETTY_PRINT, self::DEPTH);
    }

    public static function decode(string $jsonString): array
    {
        return json_decode($jsonString, true, self::DEPTH, self::DECODE_FLAGS);
    }
}
