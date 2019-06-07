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
    /**
     * @param string $displayName
     *
     * @return string
     */
    public static function displayName($displayName)
    {
        self::requireUtf8($displayName, 'displayName');

        $displayName = filter_var($displayName, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);

        if (0 === mb_strlen($displayName)) {
            throw new InputValidationException('invalid "display_name"');
        }

        return $displayName;
    }

    /**
     * @param string $commonName
     *
     * @return string
     */
    public static function commonName($commonName)
    {
        if (1 !== preg_match('/^[a-fA-F0-9]{32}$/', $commonName)) {
            throw new InputValidationException('invalid "common_name"');
        }

        return $commonName;
    }

    /**
     * @param string $profileId
     *
     * @return string
     */
    public static function profileId($profileId)
    {
        if (1 !== preg_match('/^[a-zA-Z0-9-.]+$/', $profileId)) {
            throw new InputValidationException('invalid "profile_id"');
        }

        return $profileId;
    }

    /**
     * @param string $totpSecret
     *
     * @return string
     */
    public static function totpSecret($totpSecret)
    {
        if (1 !== preg_match('/^[A-Z0-9]{32}$/', $totpSecret)) {
            throw new InputValidationException('invalid "totp_secret"');
        }

        return $totpSecret;
    }

    /**
     * @param string $totpKey
     *
     * @return string
     */
    public static function totpKey($totpKey)
    {
        if (1 !== preg_match('/^[0-9]{6}$/', $totpKey)) {
            throw new InputValidationException('invalid "totp_key"');
        }

        return $totpKey;
    }

    /**
     * @param string $clientId
     *
     * @return string
     */
    public static function clientId($clientId)
    {
        if (1 !== preg_match('/^(?:[\x20-\x7E])+$/', $clientId)) {
            throw new InputValidationException('invalid "client_id"');
        }

        return $clientId;
    }

    /**
     * @param string $dateTime
     *
     * @return \DateTime
     */
    public static function dateTime($dateTime)
    {
        if (false === $dateTimeObj = DateTime::createFromFormat('Y-m-d H:i:s', $dateTime)) {
            throw new InputValidationException('invalid "date_time"');
        }

        return $dateTimeObj;
    }

    /**
     * @param string $userId
     *
     * @return string
     */
    public static function userId($userId)
    {
        self::requireUtf8($userId, 'userId');

        $userIdLength = mb_strlen($userId);
        if (0 >= $userIdLength || 256 < $userIdLength) {
            throw new InputValidationException('invalid length: 0 < userId <= 256');
        }

        return $userId;
    }

    /**
     * @param string $ipAddress
     *
     * @return string
     */
    public static function ipAddress($ipAddress)
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new InputValidationException('invalid "ip_address"');
        }

        // normalize the IP address (only makes a difference for IPv6)
        return inet_ntop(inet_pton($ipAddress));
    }

    /**
     * @param string $messageId
     *
     * @return int
     */
    public static function messageId($messageId)
    {
        if (!is_numeric($messageId) || 0 >= $messageId) {
            throw new InputValidationException('invalid "message_id"');
        }

        return (int) $messageId;
    }

    /**
     * @param string $userPass
     *
     * @return string
     */
    public static function userPass($userPass)
    {
        self::requireUtf8($userPass, 'userPass');

        $userPassLength = mb_strlen($userPass);
        if (8 > $userPassLength) {
            throw new InputValidationException('password MUST be at least 8 characters long');
        }

        return $userPass;
    }

    /**
     * @param string $systemMessage
     *
     * @return string
     */
    public static function systemMessage($systemMessage)
    {
        self::requireUtf8($systemMessage, 'systemMessage');

        if (0 === mb_strlen($systemMessage)) {
            throw new InputValidationException('invalid "system_message"');
        }

        return $systemMessage;
    }

    /**
     * @param string $uiLang
     *
     * @return string
     */
    public static function uiLang($uiLang)
    {
        if (1 !== preg_match('/^[a-z]{2}_[A-Z]{2}$/', $uiLang)) {
            throw new InputValidationException('invalid "ui_lang"');
        }

        return $uiLang;
    }

    /**
     * @param string $inputString
     * @param string $inputName
     *
     * @return void
     */
    private static function requireUtf8($inputString, $inputName)
    {
        // we want valid UTF-8
        if (!mb_check_encoding($inputString, 'UTF-8')) {
            throw new InputValidationException(sprintf('invalid encoding for "%s"', $inputName));
        }
    }
}
