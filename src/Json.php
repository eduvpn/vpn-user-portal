<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\Exception\JsonException;

class Json
{
    public static function encode(array $jsonData): string
    {
        $jsonString = json_encode($jsonData);
        // 5.5.0 	The return value on failure was changed from null string to FALSE.
        if (false === $jsonString || 'null' === $jsonString) {
            throw new JsonException('unable to encode JSON');
        }

        return $jsonString;
    }

    public static function decode(string $jsonString): array
    {
        $jsonData = json_decode($jsonString, true);
        if (null === $jsonData && JSON_ERROR_NONE !== json_last_error()) {
            throw new JsonException('unable to parse/decode JSON');
        }

        if (!\is_array($jsonData)) {
            throw new JsonException(sprintf('expected JSON object, got "%s"', \gettype($jsonData)));
        }

        return $jsonData;
    }
}
