<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateTime;
use LC\Portal\Http\Exception\InputValidationException;

class InputValidation
{
    public static function displayName(string $displayName): string
    {
        self::requireUtf8($displayName, 'displayName');

        $displayName = filter_var($displayName, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);

        if (0 === mb_strlen($displayName)) {
            throw new InputValidationException('invalid "display_name"');
        }

        return $displayName;
    }

    public static function commonName(string $commonName): string
    {
        if (1 !== preg_match('/^[a-fA-F0-9]{32}$/', $commonName)) {
            throw new InputValidationException('invalid "common_name"');
        }

        return $commonName;
    }

    public static function profileId(string $profileId): string
    {
        if (1 !== preg_match('/^[a-zA-Z0-9-.]+$/', $profileId)) {
            throw new InputValidationException('invalid "profile_id"');
        }

        return $profileId;
    }

    public static function totpSecret(string $totpSecret): string
    {
        if (1 !== preg_match('/^[A-Z0-9]{32}$/', $totpSecret)) {
            throw new InputValidationException('invalid "totp_secret"');
        }

        return $totpSecret;
    }

    public static function totpKey(string $totpKey): string
    {
        if (1 !== preg_match('/^[0-9]{6}$/', $totpKey)) {
            throw new InputValidationException('invalid "totp_key"');
        }

        return $totpKey;
    }

    public static function clientId(string $clientId): string
    {
        if (1 !== preg_match('/^(?:[\x20-\x7E])+$/', $clientId)) {
            throw new InputValidationException('invalid "client_id"');
        }

        return $clientId;
    }

    public static function dateTime(string $dateTime): DateTime
    {
        if (false === $dateTimeObj = DateTime::createFromFormat('Y-m-d H:i:s', $dateTime)) {
            throw new InputValidationException('invalid "date_time"');
        }

        return $dateTimeObj;
    }

    public static function userId(string $userId): string
    {
        self::requireUtf8($userId, 'userId');

        $userIdLength = mb_strlen($userId);
        if (0 >= $userIdLength || 256 < $userIdLength) {
            throw new InputValidationException('invalid length: 0 < userId <= 256');
        }

        return $userId;
    }

    public static function ipAddress(string $ipAddress): string
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new InputValidationException('invalid "ip_address"');
        }

        // normalize the IP address (only makes a difference for IPv6)
        return inet_ntop(inet_pton($ipAddress));
    }

    public static function messageId(string $messageId): int
    {
        if (!is_numeric($messageId) || 0 >= $messageId) {
            throw new InputValidationException('invalid "message_id"');
        }

        return (int) $messageId;
    }

    public static function userPass(string $userPass): string
    {
        self::requireUtf8($userPass, 'userPass');

        $userPassLength = mb_strlen($userPass);
        if (8 > $userPassLength) {
            throw new InputValidationException('password MUST be at least 8 characters long');
        }

        return $userPass;
    }

    public static function systemMessage(string $systemMessage): string
    {
        self::requireUtf8($systemMessage, 'systemMessage');

        if (0 === mb_strlen($systemMessage)) {
            throw new InputValidationException('invalid "system_message"');
        }

        return $systemMessage;
    }

    public static function uiLang(string $uiLang): string
    {
        if (1 !== preg_match('/^[a-z]{2}_[A-Z]{2}$/', $uiLang)) {
            throw new InputValidationException('invalid "ui_lang"');
        }

        return $uiLang;
    }

    private static function requireUtf8(string $inputString, string $inputName): void
    {
        // we want valid UTF-8
        if (!mb_check_encoding($inputString, 'UTF-8')) {
            throw new InputValidationException(sprintf('invalid encoding for "%s"', $inputName));
        }
    }
}
