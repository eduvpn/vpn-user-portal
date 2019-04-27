<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Exception\HttpException;

class Utils
{
    /**
     * @param array  $sourceData
     * @param string $key
     * @param bool   $isRequired
     * @param mixed  $defaultValue
     *
     * @return mixed
     */
    public static function getValueFromArray(array $sourceData, $key, $isRequired, $defaultValue)
    {
        if (\array_key_exists($key, $sourceData)) {
            return $sourceData[$key];
        }

        if ($isRequired) {
            throw new HttpException(
                sprintf('missing required field "%s"', $key),
                400
            );
        }

        return $defaultValue;
    }
}
