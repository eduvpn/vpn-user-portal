<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\VPN\UserPortal;

use fkooman\Http\Exception\BadRequestException;

class Utils
{
    public static function validateConfigName($configName)
    {
        if (null === $configName) {
            throw new BadRequestException('missing parameter');
        }
        if (!is_string($configName)) {
            throw new BadRequestException('malformed parameter');
        }
        if (32 < strlen($configName)) {
            throw new BadRequestException('name too long, maximum 32 characters');
        }
        // XXX: be less restrictive in supported characters...
        if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $configName)) {
            throw new BadRequestException('invalid characters in name');
        }
    }

    public static function validateString($input)
    {
        if (!is_string($input)) {
            throw new BadRequestException('parameter must be string');
        }
        if (0 >= strlen($input)) {
            throw new BadRequestException('parameter must be non-empty string');
        }
    }

    public static function validatePoolId($poolId)
    {
        self::validateString($poolId);
        $matchPattern = '/^[a-zA-Z0-9]+$/';
        if (1 !== preg_match($matchPattern, $poolId)) {
            throw new BadRequestException(
                sprintf('parameter must match pattern "%s"', $matchPattern)
            );
        }
    }

    public static function validateLanguage($language)
    {
        $supportedLanguages = ['en_US', 'nl_NL', 'de_DE', 'fr_FR'];
        if (!in_array($language, $supportedLanguages)) {
            throw new BadRequestException('unsupported language');
        }
    }
}
