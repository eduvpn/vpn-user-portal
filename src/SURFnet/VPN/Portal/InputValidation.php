<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace SURFnet\VPN\Portal;

use SURFnet\VPN\Common\Http\Exception\HttpException;

class InputValidation
{
    public static function configName($configName)
    {
        self::validateString($configName);

        if (32 < strlen($configName)) {
            throw new HttpException('invalid configName (too long)', 400);
        }
        if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $configName)) {
            throw new HttpException('invalid configName (invalid characters)', 400);
        }
    }

    public static function poolId($poolId)
    {
        self::validateString($poolId);

        if (1 !== preg_match('/^[a-zA-Z0-9]+$/', $poolId)) {
            throw new HttpException('invalid poolId (invalid characters)', 400);
        }
    }

    public static function setLanguage($setLanguage)
    {
        self::validateString($setLanguage);

        $supportedLanguages = ['en_US', 'nl_NL', 'de_DE', 'fr_FR'];
        if (!in_array($setLanguage, $supportedLanguages)) {
            throw new HttpException('invalid setLanguage (not supported)', 400);
        }
    }

    public static function confirmDisable($confirmDisable)
    {
        self::validateString($confirmDisable);

        if (!in_array($confirmDisable, ['yes', 'no'])) {
            throw new HttpException('invalid confirmDisable (not supported)', 400);
        }
    }

    private static function validateString($input)
    {
        if (!is_string($input)) {
            throw new HttpException('parameter must be string', 400);
        }
        if (0 >= strlen($input)) {
            throw new HttpException('parameter must be non-empty string', 400);
        }
    }

    public static function otpSecret($otpSecret)
    {
        if (0 === preg_match('/^[A-Z0-9]{16}$/', $otpSecret)) {
            throw new HttpException('invalid OTP secret format', 400);
        }
    }

    public static function otpKey($otpKey)
    {
        if (0 === preg_match('/^[0-9]{6}$/', $otpKey)) {
            throw new HttpException('invalid OTP key format', 400);
        }
    }
}
